<?php

namespace Husail\EdiSdk\Schema;

use Husail\EdiSdk\Exceptions\LayoutException;

/**
 * Describes one fixed-width record layout.
 *
 * Fluent builder example:
 *
 *   $layout = RecordLayout::define('file_header')
 *       ->lineLength(240)
 *       ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
 *       ->addField('company_name', pos: 73, len: 30, type: FieldType::ALPHA)
 *       ->build();
 *
 * Direct construction (used by JSON/YAML drivers):
 *   RecordLayout::fromArray('file_header', 240, $fields, $validators)
 */
final class RecordLayout
{
    /** @var array<int, FieldDefinition> */
    private array $fields = [];

    /** @var array<int, callable(array<string, string>): ?string> */
    private array $validators = [];

    private int $lineLength = 0;

    private function __construct(
        public readonly string $name,
    ) {
    }

    public static function define(string $name): self
    {
        return new self($name);
    }

    public function lineLength(int $length): self
    {
        $clone             = clone $this;
        $clone->lineLength = $length;

        return $clone;
    }

    public function addField(
        string       $name,
        int          $pos,
        int          $len,
        FieldType    $type,
        ?string      $const         = null,
        ?string      $default       = null,
        ?string      $format        = null,
        ?string      $cast          = null,
        int          $decimalPlaces = 0,
        bool         $required      = true,
        ?string      $paddingChar   = null,
        ?PaddingSide $paddingSide   = null,
    ): self {
        $clone           = clone $this;
        $clone->fields[] = new FieldDefinition(
            name:          $name,
            position:      $pos,
            length:        $len,
            type:          $type,
            const:         $const,
            default:       $default,
            format:        $format,
            cast:          $cast,
            decimalPlaces: $decimalPlaces,
            required:      $required,
            paddingChar:   $paddingChar,
            paddingSide:   $paddingSide,
        );

        return $clone;
    }

    /**
     * Adds a custom record validator.
     *
     * The callable receives raw line data (`field => raw value`) and returns
     * an error message or null.
     *
     * @param callable(array<string, string>): ?string $validator
     */
    public function addValidator(callable $validator): self
    {
        $clone               = clone $this;
        $clone->validators[] = $validator;

        return $clone;
    }

    /**
     * @throws LayoutException when structural invariants are violated.
     */
    public function build(): self
    {
        $this->assertValid($this->name, $this->lineLength, $this->fields);

        return $this;
    }

    /**
     * @param FieldDefinition[] $fields
     * @param array<int, callable(array<string, string>): ?string> $validators
     *
     * @throws LayoutException when structural invariants are violated.
     */
    public static function fromArray(
        string $name,
        int    $lineLength,
        array  $fields,
        array  $validators = [],
    ): self {
        self::assertValid($name, $lineLength, $fields);

        $instance             = new self($name);
        $instance->lineLength = $lineLength;
        $instance->fields     = $fields;
        $instance->validators = $validators;

        return $instance;
    }

    public function getLineLength(): int
    {
        return $this->lineLength;
    }

    /** @return array<int, FieldDefinition> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Returns a field by name, or null when it does not exist.
     */
    public function getField(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** @return array<int, callable(array<string, string>): ?string> */
    public function getValidators(): array
    {
        return $this->validators;
    }

    /**
     * Validates RecordLayout structural invariants.
     * Called by both build() and fromArray().
     *
     * @param FieldDefinition[] $fields
     */
    private static function assertValid(string $name, int $lineLength, array $fields): void
    {
        if ($lineLength <= 0) {
            throw new LayoutException(
                "RecordLayout '{$name}': lineLength must be > 0."
            );
        }

        if (empty($fields)) {
            throw new LayoutException(
                "RecordLayout '{$name}': at least one field must be defined."
            );
        }

        $seenNames     = []; // name → true, for duplicate detection
        $occupiedBytes = []; // offset → field name, for overlap detection

        foreach ($fields as $field) {
            if (isset($seenNames[$field->name])) {
                throw new LayoutException(
                    "RecordLayout '{$name}': duplicate field name '{$field->name}'."
                );
            }
            $seenNames[$field->name] = true;

            if ($field->position <= 0) {
                throw new LayoutException(
                    "RecordLayout '{$name}', field '{$field->name}': position must be > 0, got {$field->position}."
                );
            }

            if ($field->length <= 0) {
                throw new LayoutException(
                    "RecordLayout '{$name}', field '{$field->name}': length must be > 0, got {$field->length}."
                );
            }

            $end = $field->offset() + $field->length;
            if ($end > $lineLength) {
                throw new LayoutException(
                    "RecordLayout '{$name}', field '{$field->name}': "
                    . "occupies bytes {$field->position}–{$end} but lineLength is {$lineLength}."
                );
            }

            for ($i = $field->offset(); $i < $end; $i++) {
                if (isset($occupiedBytes[$i])) {
                    throw new LayoutException(
                        "RecordLayout '{$name}', field '{$field->name}': "
                        . "overlaps with field '{$occupiedBytes[$i]}' at byte " . ($i + 1) . "."
                    );
                }
                $occupiedBytes[$i] = $field->name;
            }

            if ($field->cast === 'date' && $field->format === null) {
                throw new LayoutException(
                    "RecordLayout '{$name}', field '{$field->name}': cast='date' requires 'format' to be defined."
                );
            }
        }
    }
}
