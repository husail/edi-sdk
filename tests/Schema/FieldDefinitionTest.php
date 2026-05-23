<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Schema\FieldDefinition;

describe('FieldDefinition', function () {

    describe('offset()', function () {
        it('converts 1-based position to 0-based offset', function () {
            $field = simpleField(pos: 1);
            expect($field->offset())->toBe(0);
        });

        it('converts position 8 to offset 7', function () {
            $field = simpleField(pos: 8);
            expect($field->offset())->toBe(7);
        });

        it('converts position 73 to offset 72', function () {
            $field = simpleField(pos: 73);
            expect($field->offset())->toBe(72);
        });
    });

    describe('resolvedPaddingChar()', function () {
        it('returns space for ALPHA when paddingChar is null', function () {
            $field = simpleField(type: FieldType::ALPHA);
            expect($field->resolvedPaddingChar())->toBe(' ');
        });

        it('returns zero for NUMERIC when paddingChar is null', function () {
            $field = simpleField(type: FieldType::NUMERIC);
            expect($field->resolvedPaddingChar())->toBe('0');
        });

        it('returns custom paddingChar when explicitly set', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 5,
                type: FieldType::ALPHA,
                paddingChar: '*'
            );
            expect($field->resolvedPaddingChar())->toBe('*');
        });
    });

    describe('resolvedPaddingSide()', function () {
        it('returns RIGHT for ALPHA when paddingSide is null', function () {
            $field = simpleField(type: FieldType::ALPHA);
            expect($field->resolvedPaddingSide())->toBe(PaddingSide::RIGHT);
        });

        it('returns LEFT for NUMERIC when paddingSide is null', function () {
            $field = simpleField(type: FieldType::NUMERIC);
            expect($field->resolvedPaddingSide())->toBe(PaddingSide::LEFT);
        });

        it('returns custom paddingSide when explicitly set', function () {
            $field = new FieldDefinition(
                name: 'f',
                position: 1,
                length: 5,
                type: FieldType::NUMERIC,
                paddingSide: PaddingSide::RIGHT
            );
            expect($field->resolvedPaddingSide())->toBe(PaddingSide::RIGHT);
        });
    });

    it('stores all constructor values correctly', function () {
        $field = new FieldDefinition(
            name:          'amount',
            position:      120,
            length:        15,
            type:          FieldType::NUMERIC,
            const:         null,
            default:       '0',
            format:        null,
            cast:          'float',
            decimalPlaces: 2,
            required:      true,
        );

        expect($field->name)->toBe('amount');
        expect($field->position)->toBe(120);
        expect($field->length)->toBe(15);
        expect($field->type)->toBe(FieldType::NUMERIC);
        expect($field->cast)->toBe('float');
        expect($field->decimalPlaces)->toBe(2);
        expect($field->default)->toBe('0');
    });
});
