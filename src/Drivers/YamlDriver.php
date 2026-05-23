<?php

namespace Husail\EdiSdk\Drivers;

use Symfony\Component\Yaml\Yaml;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Exceptions\LayoutException;
use Husail\EdiSdk\Contracts\LayoutDriverInterface;
use Husail\EdiSdk\Schema\Mapping\ArrayLayoutMapper;
use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;

/**
 * Loads a FileLayout from a YAML file or YAML string.
 *
 * Responsible only for parsing YAML → array.
 * Building the FileLayout is delegated to ArrayLayoutMapper.
 *
 * Usage:
 *   $layout = (new YamlDriver())->load('/path/to/cnab240.yaml');
 *   $layout = (new YamlDriver())->load("name: cnab240\n...");
 */
final class YamlDriver implements LayoutDriverInterface
{
    private ArrayLayoutMapper $mapper;

    public function __construct()
    {
        $this->mapper = new ArrayLayoutMapper();
    }

    /**
     * @param string $source Path to a YAML file or a raw YAML string.
     *
     * @throws LayoutException when the source cannot be read or parsed.
     */
    public function load(mixed $source): FileLayout
    {
        return $this->mapper->map($this->parse($source));
    }

    /** @return array<string, mixed> */
    private function parse(string $source): array
    {
        try {
            $data = !str_contains($source, "\n") && file_exists($source)
                ? Yaml::parseFile($source)
                : Yaml::parse($source);
        } catch (YamlParseException $e) {
            throw new LayoutException("Invalid YAML layout: " . $e->getMessage(), previous: $e);
        }

        if (!is_array($data)) {
            throw new LayoutException("YAML layout must be a mapping (associative array at root).");
        }

        return $data;
    }
}
