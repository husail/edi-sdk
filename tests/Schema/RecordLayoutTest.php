<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Exceptions\LayoutException;

describe('RecordLayout', function () {

    describe('builder', function () {
        it('creates a valid layout via fluent builder', function () {
            $layout = RecordLayout::define('header')
                ->lineLength(10)
                ->addField('code', pos: 1, len: 3, type: FieldType::NUMERIC)
                ->addField('name', pos: 4, len: 7, type: FieldType::ALPHA)
                ->build();

            expect($layout->name)->toBe('header');
            expect($layout->getLineLength())->toBe(10);
            expect($layout->getFields())->toHaveCount(2);
        });

        it('is immutable — each call returns a new instance', function () {
            $base      = RecordLayout::define('record')->lineLength(10);
            $withField = $base->addField('f', pos: 1, len: 10, type: FieldType::ALPHA);

            expect($base->getFields())->toHaveCount(0);
            expect($withField->getFields())->toHaveCount(1);
        });

        it('accumulates fields across multiple addField calls', function () {
            $layout = RecordLayout::define('record')
                ->lineLength(30)
                ->addField('a', pos: 1, len: 10, type: FieldType::ALPHA)
                ->addField('b', pos: 11, len: 10, type: FieldType::ALPHA)
                ->addField('c', pos: 21, len: 10, type: FieldType::ALPHA)
                ->build();

            expect($layout->getFields())->toHaveCount(3);
        });

        it('throws LayoutException when lineLength is not set', function () {
            expect(
                fn () => RecordLayout::define('record')
                    ->addField('f', pos: 1, len: 5, type: FieldType::ALPHA)
                    ->build()
            )->toThrow(LayoutException::class, 'lineLength');
        });

        it('throws LayoutException when no fields are defined', function () {
            expect(
                fn () => RecordLayout::define('record')
                    ->lineLength(10)
                    ->build()
            )->toThrow(LayoutException::class);
        });

        it('throws when field position is zero', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(10)
                    ->addField('f', pos: 0, len: 5, type: FieldType::ALPHA)
                    ->build()
            )->toThrow(LayoutException::class, 'position');
        });

        it('throws when field length is zero', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(10)
                    ->addField('f', pos: 1, len: 0, type: FieldType::ALPHA)
                    ->build()
            )->toThrow(LayoutException::class, 'length');
        });

        it('throws when field exceeds line length', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(10)
                    ->addField('f', pos: 8, len: 5, type: FieldType::ALPHA) // bytes 8–12, linha só tem 10
                    ->build()
            )->toThrow(LayoutException::class, 'lineLength');
        });

        it('throws when two fields overlap', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(20)
                    ->addField('a', pos: 1, len: 10, type: FieldType::ALPHA)
                    ->addField('b', pos: 5, len: 10, type: FieldType::ALPHA) // sobrepõe 'a' nos bytes 5–10
                    ->build()
            )->toThrow(LayoutException::class, 'overlaps');
        });

        it('throws when two fields have the same name', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(20)
                    ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA)
                    ->addField('type', pos: 2, len: 9, type: FieldType::ALPHA)
                    ->build()
            )->toThrow(LayoutException::class, 'duplicate');
        });

        it('throws when cast=date but format is missing', function () {
            expect(
                fn () => RecordLayout::define('rec')
                    ->lineLength(10)
                    ->addField('date', pos: 1, len: 8, type: FieldType::NUMERIC, cast: 'date')
                    ->build()
            )->toThrow(LayoutException::class, 'format');
        });

        it('accepts fields that exactly fill the line', function () {
            $layout = RecordLayout::define('rec')
                ->lineLength(10)
                ->addField('a', pos: 1, len: 5, type: FieldType::ALPHA)
                ->addField('b', pos: 6, len: 5, type: FieldType::ALPHA)
                ->build();

            expect($layout->getFields())->toHaveCount(2);
        });

        it('accepts cast=date when format is provided', function () {
            $layout = RecordLayout::define('rec')
                ->lineLength(10)
                ->addField('date', pos: 1, len: 8, type: FieldType::NUMERIC, cast: 'date', format: 'dmY')
                ->addField('fill', pos: 9, len: 2, type: FieldType::ALPHA, required: false)
                ->build();

            expect($layout->getField('date')?->format)->toBe('dmY');
        });
    });

    describe('getField()', function () {
        it('returns field by name', function () {
            $layout = RecordLayout::define('record')
                ->lineLength(10)
                ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC)
                ->addField('name', pos: 4, len: 7, type: FieldType::ALPHA)
                ->build();

            $field = $layout->getField('bank_code');

            expect($field)->not->toBeNull();
            expect($field->name)->toBe('bank_code');
        });

        it('returns null when field does not exist', function () {
            $layout = RecordLayout::define('record')
                ->lineLength(10)
                ->addField('code', pos: 1, len: 10, type: FieldType::ALPHA)
                ->build();

            expect($layout->getField('nonexistent'))->toBeNull();
        });
    });

    describe('addValidator()', function () {
        it('stores custom validators', function () {
            $layout = RecordLayout::define('record')
                ->lineLength(10)
                ->addField('code', pos: 1, len: 10, type: FieldType::ALPHA)
                ->addValidator(fn (array $data) => null)
                ->addValidator(fn (array $data) => null)
                ->build();

            expect($layout->getValidators())->toHaveCount(2);
        });

        it('is immutable — each addValidator returns new instance', function () {
            $base = RecordLayout::define('record')
                ->lineLength(10)
                ->addField('f', pos: 1, len: 10, type: FieldType::ALPHA);

            $withValidator = $base->addValidator(fn ($d) => null);

            expect($base->getValidators())->toHaveCount(0);
            expect($withValidator->getValidators())->toHaveCount(1);
        });
    });

    describe('fromArray()', function () {
        it('creates layout from array (used by drivers)', function () {
            $fields = [
                simpleField('code', pos: 1, len: 3, type: FieldType::NUMERIC),
                simpleField('name', pos: 4, len: 7, type: FieldType::ALPHA),
            ];

            $layout = RecordLayout::fromArray('header', 10, $fields);

            expect($layout->name)->toBe('header');
            expect($layout->getLineLength())->toBe(10);
            expect($layout->getFields())->toHaveCount(2);
        });

        it('applies the same validations as build()', function () {
            $fields = [
                new FieldDefinition(name: 'a', position: 1, length: 5, type: FieldType::ALPHA),
                new FieldDefinition(name: 'b', position: 3, length: 5, type: FieldType::ALPHA), // sobrepõe 'a'
            ];

            expect(fn () => RecordLayout::fromArray('rec', 10, $fields))
                ->toThrow(LayoutException::class, 'overlaps');
        });

        it('throws when lineLength is zero via fromArray', function () {
            $fields = [
                new FieldDefinition(name: 'f', position: 1, length: 5, type: FieldType::ALPHA),
            ];

            expect(fn () => RecordLayout::fromArray('rec', 0, $fields))
                ->toThrow(LayoutException::class, 'lineLength');
        });
    });
});
