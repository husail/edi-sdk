<?php

namespace Husail\EdiSdk\Schema;

/**
 * Describes a single field in a positional record.
 *
 * Immutable after construction. Does not execute formatting — only describes
 * how formatting should be done. The engine reads these metadata.
 *
 * Position:
 *   1-based, following the CNAB specification and most bank layouts.
 *   The engine converts to 0-based internally via offset().
 *
 * Parser cast:
 *   null → raw string (no conversion)
 *   'int' → (int) after trim
 *   'float' → (float) considering $decimalPlaces
 *   'date' → \DateTimeImmutable via $format (required when cast='date')
 */
final readonly class FieldDefinition
{
    public function __construct(
        /** Field name — key in the parser result array. */
        public string $name,

        /** Start position, 1-based. */
        public int $position,

        /** Length in characters. */
        public int $length,

        /** Field type — determines default padding and serialization behavior. */
        public FieldType $type,

        /**
         * Constant value.
         *   Writer: ignores the provided data and uses this value.
         *   Validator: checks that the field contains exactly this value.
         */
        public ?string $const = null,

        /**
         * Default value used by the Writer when the provided data is null or empty.
         * Ignored when $const is defined.
         */
        public ?string $default = null,

        /**
         * Date/time format compatible with date() and \DateTimeImmutable::createFromFormat().
         * Examples: 'dmY' for DDMMAAAA, 'His' for HHMMSS.
         * Required when $cast = 'date'.
         */
        public ?string $format = null,

        /**
         * Conversion applied by the parser to the read value.
         * Accepted values: null, 'int', 'float', 'date'.
         */
        public ?string $cast = null,

        /**
         * Implicit decimal places for values with an assumed decimal point.
         * Example: $decimalPlaces=2 → "000000000012345" represents 123.45.
         * Used with cast='float' in the parser and when serializing floats in the writer.
         */
        public int $decimalPlaces = 0,

        /**
         * When false, the Validator emits an error when the ALPHA field is empty after trim.
         * The Writer always fills with padding regardless of this setting.
         */
        public bool $required = true,

        /**
         * Padding character. Null uses the type default:
         *   ALPHA → ' '
         *   NUMERIC → '0'
         */
        public ?string $paddingChar = null,

        /**
         * Padding side. Null uses the type default:
         *   ALPHA → RIGHT
         *   NUMERIC → LEFT
         */
        public ?PaddingSide $paddingSide = null,
    ) {
    }

    /** Effective padding char considering the type default. */
    public function resolvedPaddingChar(): string
    {
        return $this->paddingChar ?? $this->type->defaultPaddingChar();
    }

    /** Effective padding side considering the type default. */
    public function resolvedPaddingSide(): PaddingSide
    {
        return $this->paddingSide ?? $this->type->defaultPaddingSide();
    }

    /** 0-based position for internal engine use. */
    public function offset(): int
    {
        return $this->position - 1;
    }
}
