<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Engine\FieldSerializer;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Exceptions\WriteException;

describe('FieldSerializer', function () {

    describe('serialize() — ALPHA fields', function () {
        it('pads with spaces on the right to fill length', function () {
            $field = simpleField(len: 10, type: FieldType::ALPHA);
            expect(FieldSerializer::serialize('ABC', $field))->toBe('ABC       ');
        });

        it('converts to uppercase', function () {
            $field = simpleField(len: 5, type: FieldType::ALPHA);
            expect(FieldSerializer::serialize('hello', $field))->toBe('HELLO');
        });

        it('truncates value to field length', function () {
            $field = simpleField(len: 5, type: FieldType::ALPHA);
            expect(FieldSerializer::serialize('ABCDEFGH', $field))->toBe('ABCDE');
        });

        it('uses empty string when value is null', function () {
            $field = simpleField(len: 5, type: FieldType::ALPHA);
            expect(FieldSerializer::serialize(null, $field))->toBe('     ');
        });

        it('uses default when value is null and default is set', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 5,
                type: FieldType::ALPHA,
                default: 'DEF'
            );
            expect(FieldSerializer::serialize(null, $field))->toBe('DEF  ');
        });

        it('const overrides any provided value', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 3,
                type: FieldType::NUMERIC,
                const: '341'
            );
            expect(FieldSerializer::serialize('999', $field))->toBe('341');
        });

        it('const overrides null value', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 3,
                type: FieldType::NUMERIC,
                const: '341'
            );
            expect(FieldSerializer::serialize(null, $field))->toBe('341');
        });
    });

    describe('serialize() — NUMERIC fields', function () {
        it('pads with zeros on the left', function () {
            $field = simpleField(len: 6, type: FieldType::NUMERIC);
            expect(FieldSerializer::serialize('341', $field))->toBe('000341');
        });

        it('strips non-digit characters', function () {
            $field = simpleField(len: 11, type: FieldType::NUMERIC);
            expect(FieldSerializer::serialize('123.456.789-0', $field))->toBe('01234567890'); // strip não-dígitos → '1234567890' (10) → pad left zeros → '01234567890'
        });

        it('handles integer values', function () {
            $field = simpleField(len: 8, type: FieldType::NUMERIC);
            expect(FieldSerializer::serialize(12345, $field))->toBe('00012345');
        });

        it('handles float with decimalPlaces', function () {
            $field = new FieldDefinition(
                name: 'amount',
                position: 1,
                length: 15,
                type: FieldType::NUMERIC,
                decimalPlaces: 2
            );
            expect(FieldSerializer::serialize(150.75, $field))->toBe('000000000015075');
        });

        it('rounds correctly with decimalPlaces', function () {
            $field = new FieldDefinition(
                name: 'amount',
                position: 1,
                length: 15,
                type: FieldType::NUMERIC,
                decimalPlaces: 2
            );
            expect(FieldSerializer::serialize(0.01, $field))->toBe('000000000000001');
        });
    });

    describe('serialize() — custom padding', function () {
        it('respects custom paddingChar', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 5,
                type: FieldType::ALPHA,
                paddingChar: '0'
            );
            expect(FieldSerializer::serialize('AB', $field))->toBe('AB000');
        });

        it('respects custom paddingSide LEFT for ALPHA', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 5,
                type: FieldType::ALPHA,
                paddingSide: PaddingSide::LEFT
            );
            expect(FieldSerializer::serialize('AB', $field))->toBe('   AB');
        });
    });

    describe('deserialize() — raw string (no cast)', function () {
        it('extracts raw field value from line', function () {
            $line  = '341000000001';
            $field = simpleField(pos: 1, len: 3, type: FieldType::NUMERIC);
            expect(FieldSerializer::deserialize($line, $field))->toBe('341');
        });

        it('extracts field at correct offset', function () {
            $line  = '341000000001';
            $field = simpleField(pos: 4, len: 9, type: FieldType::NUMERIC);
            expect(FieldSerializer::deserialize($line, $field))->toBe('000000001');
        });
    });

    describe('deserialize() — cast int', function () {
        it('trims and casts to int', function () {
            $line  = '000341';
            $field = simpleField(pos: 1, len: 6, type: FieldType::NUMERIC, cast: 'int');
            expect(FieldSerializer::deserialize($line, $field))->toBe(341);
        });
    });

    describe('deserialize() — cast float with decimalPlaces', function () {
        it('divides by 10^decimalPlaces', function () {
            $line  = '000000000015075';
            $field = new FieldDefinition(
                name: 'amount',
                position: 1,
                length: 15,
                type: FieldType::NUMERIC,
                cast: 'float',
                decimalPlaces: 2
            );
            expect(FieldSerializer::deserialize($line, $field))->toBe(150.75);
        });

        it('returns float without division when decimalPlaces is 0', function () {
            $line  = '000001234';
            $field = new FieldDefinition(
                name: 'n',
                position: 1,
                length: 9,
                type: FieldType::NUMERIC,
                cast: 'float',
                decimalPlaces: 0
            );
            expect(FieldSerializer::deserialize($line, $field))->toBe(1234.0);
        });
    });

    describe('deserialize() — cast date', function () {
        it('parses date with format dmY', function () {
            $line  = '28042026';
            $field = new FieldDefinition(
                name: 'date',
                position: 1,
                length: 8,
                type: FieldType::NUMERIC,
                cast: 'date',
                format: 'dmY'
            );

            $result = FieldSerializer::deserialize($line, $field);

            expect($result)->toBeInstanceOf(\DateTimeImmutable::class);
            expect($result->format('d/m/Y'))->toBe('28/04/2026');
        });
    });
});
