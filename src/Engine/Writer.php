<?php

namespace Husail\EdiSdk\Engine;

use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Exceptions\WriteException;

/**
 * Writes EDI content from structured data.
 */
final class Writer
{
    /** @var string[] */
    private array $lines = [];

    public function __construct(
        private readonly FileLayout $layout,
    ) {
    }

    /**
     * Appends one record line.
     *
     * @param string $record RecordLayout name.
     * @param array<string, mixed> $data Record values keyed by field name.
     *
     * @throws WriteException when record is unknown or a value exceeds field width.
     */
    public function add(string $record, array $data): self
    {
        $recordLayout = $this->layout->resolveRecord($record);
        $line         = str_repeat(' ', $recordLayout->getLineLength());

        foreach ($recordLayout->getFields() as $field) {
            $value     = $data[$field->name] ?? null;
            $formatted = FieldSerializer::serialize($value, $field);
            $line      = substr_replace($line, $formatted, $field->offset(), $field->length);
        }

        $this->lines[] = $line;

        return $this;
    }

    public function toString(): string
    {
        return implode($this->layout->getLineEnding(), $this->lines)
            . $this->layout->getLineEnding();
    }

    /**
     * @throws WriteException when writing fails.
     */
    public function toFile(string $path): void
    {
        if (file_put_contents($path, $this->toString()) === false) {
            throw new WriteException("Could not write EDI file to: {$path}");
        }
    }

    /**
     * Returns a readable in-memory stream positioned at byte offset 0.
     *
     * @return resource
     */
    public function toStream(): mixed
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new WriteException("Could not open in-memory stream.");
        }

        fwrite($stream, $this->toString());
        rewind($stream);

        return $stream;
    }
}
