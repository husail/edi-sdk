<?php

namespace Husail\EdiSdk\Results;

/**
 * Represents a deserialized record from a positional file.
 *
 * Returned by ParserVisitor after each line is identified and deserialized.
 * Fields are always flat — scalar values (string, int, float, DateTimeImmutable).
 *
 * Usage:
 *   $header = $result->first('file_header');
 *   $name = $header?->get('company_name');
 *   $code = $header?->get('bank_code', default: '000');
 */
final readonly class ParsedRecord
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        private string $record,
        private array  $fields,
    ) {
    }

    /**
     * Name of the RecordLayout this line belongs to.
     */
    public function record(): string
    {
        return $this->record;
    }

    /**
     * Returns the value of a field by name.
     * Returns $default when the field does not exist in this record.
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return array_key_exists($field, $this->fields)
            ? $this->fields[$field]
            : $default;
    }

    /**
     * Returns all deserialized fields as an associative array.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
