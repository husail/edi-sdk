<?php

namespace Husail\EdiSdk\Schema;

/**
 * Field types supported in positional files.
 *
 *   ALPHA → alphanumeric; space padding on the right
 *   NUMERIC → numeric; zero padding on the left
 */
enum FieldType: string
{
    case ALPHA   = 'alpha';
    case NUMERIC = 'numeric';

    public function defaultPaddingChar(): string
    {
        return match ($this) {
            self::ALPHA   => ' ',
            self::NUMERIC => '0',
        };
    }

    public function defaultPaddingSide(): PaddingSide
    {
        return match ($this) {
            self::ALPHA   => PaddingSide::RIGHT,
            self::NUMERIC => PaddingSide::LEFT,
        };
    }
}
