<?php

namespace Husail\EdiSdk\Engine;

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Exceptions\WriteException;

/**
 * Serializes and deserializes fixed-width field values.
 *
 * Stateless — all methods are static.
 */
final class FieldSerializer
{
    private function __construct()
    {
    }

    /**
     * Converts a PHP value to the fixed-width string for the field.
     *
     * Precedence: const → default → value.
     *
     * @throws WriteException when the formatted value exceeds the field length.
     */
    public static function serialize(mixed $value, FieldDefinition $field): string
    {
        if ($field->const !== null) {
            return self::pad($field->const, $field);
        }

        if ($value === null || $value === '') {
            $value = $field->default ?? '';
        }

        $formatted = match ($field->type) {
            FieldType::ALPHA   => self::serializeAlpha($value, $field),
            FieldType::NUMERIC => self::serializeNumeric($value, $field),
        };

        if (mb_strlen($formatted) > $field->length) {
            throw new WriteException(
                "Field '{$field->name}': formatted value '{$formatted}' exceeds max length {$field->length}."
            );
        }

        return self::pad($formatted, $field);
    }

    public static function deserialize(string $line, FieldDefinition $field): mixed
    {
        $raw = mb_substr($line, $field->offset(), $field->length);

        return match ($field->cast) {
            'int'   => (int) trim($raw),
            'float' => self::deserializeFloat($raw, $field),
            'date'  => self::deserializeDate($raw, $field),
            default => $raw,
        };
    }

    private static function serializeAlpha(mixed $value, FieldDefinition $field): string
    {
        $str = mb_strtoupper((string) $value);
        $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str) ?? $str;

        return mb_substr($str, 0, $field->length);
    }

    private static function serializeNumeric(mixed $value, FieldDefinition $field): string
    {
        if ($field->decimalPlaces > 0) {
            $int = (int) round((float) $value * (10 ** $field->decimalPlaces));

            return (string) $int;
        }

        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private static function deserializeFloat(string $raw, FieldDefinition $field): float
    {
        $int = (int) trim($raw);

        return $field->decimalPlaces > 0
            ? $int / (10 ** $field->decimalPlaces)
            : (float) $int;
    }

    private static function deserializeDate(string $raw, FieldDefinition $field): \DateTimeImmutable
    {
        $format = $field->format ?? throw new \LogicException(
            "Field '{$field->name}' has cast='date' but no format defined."
        );

        $date = \DateTimeImmutable::createFromFormat($format, trim($raw));

        if ($date === false) {
            throw new \RuntimeException(
                "Field '{$field->name}': cannot parse '{$raw}' as date with format '{$format}'."
            );
        }

        return $date;
    }

    private static function pad(string $value, FieldDefinition $field): string
    {
        $char = $field->resolvedPaddingChar();
        $flag = $field->resolvedPaddingSide() === PaddingSide::LEFT
            ? STR_PAD_LEFT
            : STR_PAD_RIGHT;

        return str_pad($value, $field->length, $char, $flag);
    }
}
