<?php

namespace Husail\EdiSdk\Drivers;

use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Exceptions\LayoutException;
use Husail\EdiSdk\Contracts\LayoutDriverInterface;
use Husail\EdiSdk\Schema\Mapping\ArrayLayoutMapper;

/**
 * Loads a FileLayout from a JSON file or JSON string.
 *
 * Responsible only for parsing JSON → array.
 * Building the FileLayout is delegated to ArrayLayoutMapper.
 *
 * Usage:
 *   $layout = (new JsonDriver())->load('/path/to/cnab240.json');
 *   $layout = (new JsonDriver())->load('{"name":"cnab240",...}');
 */
final class JsonDriver implements LayoutDriverInterface
{
    private ArrayLayoutMapper $mapper;

    public function __construct()
    {
        $this->mapper = new ArrayLayoutMapper();
    }

    /**
     * @param string $source Path to a JSON file or a raw JSON string.
     *
     * @throws LayoutException when the source cannot be read or parsed.
     */
    public function load(mixed $source): FileLayout
    {
        return $this->mapper->map($this->decode($source));
    }

    /** @return array<string, mixed> */
    private function decode(string $source): array
    {
        if (!str_starts_with(trim($source), '{') && file_exists($source)) {
            $source = file_get_contents($source);
            if ($source === false) {
                throw new LayoutException("Could not read JSON layout file.");
            }
        }

        $data = json_decode($source, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LayoutException("Invalid JSON layout: " . json_last_error_msg());
        }

        return $data;
    }
}
