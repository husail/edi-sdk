<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Group;
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Exceptions\LayoutException;

describe('FileLayout', function () {

    describe('builder', function () {
        it('creates a valid layout via fluent builder', function () {
            $record = simpleRecord('header');

            $layout = FileLayout::define('test')
                ->lineLength(10)
                ->lineEnding("\r\n")
                ->addRecord($record)
                ->withSequence([Record::one($record)])
                ->build();

            expect($layout->name)->toBe('test');
            expect($layout->getLineLength())->toBe(10);
            expect($layout->getLineEnding())->toBe("\r\n");
            expect($layout->getRecords())->toHaveKey('header');
            expect($layout->getSequence())->toHaveCount(1);
        });

        it('throws LayoutException when lineLength is not set', function () {
            $record = simpleRecord('header');

            expect(
                fn () => FileLayout::define('test')
                    ->addRecord($record)
                    ->withSequence([Record::one($record)])
                    ->build()
            )->toThrow(LayoutException::class, 'lineLength');
        });

        it('throws LayoutException when sequence is empty', function () {
            $record = simpleRecord('header');

            expect(
                fn () => FileLayout::define('test')
                    ->lineLength(10)
                    ->addRecord($record)
                    ->build()
            )->toThrow(LayoutException::class);
        });

        it('throws LayoutException when sequence references unregistered record', function () {
            $registered   = simpleRecord('registered');
            $unregistered = simpleRecord('unregistered');

            expect(
                fn () => FileLayout::define('test')
                    ->lineLength(10)
                    ->addRecord($registered)
                    ->withSequence([Record::one($unregistered)])
                    ->build()
            )->toThrow(LayoutException::class, 'unregistered');
        });

        it('throws LayoutException when GroupNode has no children', function () {
            $header = simpleRecord('header');

            expect(
                fn () => FileLayout::define('test')
                    ->lineLength(10)
                    ->addRecord($header)
                    ->withSequence([
                        Group::repeat(
                            identifyBy: fn ($line) => null,
                            children: [] // vazio
                        ),
                    ])
                    ->build()
            )->toThrow(LayoutException::class, 'GroupNode');
        });

        it('throws LayoutException when AmbiguousNode has no children', function () {
            $header = simpleRecord('header');

            expect(
                fn () => FileLayout::define('test')
                    ->lineLength(10)
                    ->addRecord($header)
                    ->withSequence([
                        Group::ambiguous(
                            identifyBy: fn ($line) => null,
                            children: [] // vazio
                        ),
                    ])
                    ->build()
            )->toThrow(LayoutException::class, 'AmbiguousNode');
        });

        it('throws LayoutException when nested GroupNode references unregistered record', function () {
            $header  = simpleRecord('header');
            $unknown = simpleRecord('unknown');

            expect(
                fn () => FileLayout::define('test')
                    ->lineLength(10)
                    ->addRecord($header)
                    ->withSequence([
                        Record::one($header),
                        Group::repeat(
                            identifyBy: fn ($line) => null,
                            children: [Record::many($unknown)] // não registrado
                        ),
                    ])
                    ->build()
            )->toThrow(LayoutException::class, 'unknown');
        });

        it('is immutable — each call returns a new instance', function () {
            $base   = FileLayout::define('test')->lineLength(10);
            $record = simpleRecord('header');

            $withRecord = $base->addRecord($record);

            expect($base->getRecords())->toBeEmpty();
            expect($withRecord->getRecords())->toHaveKey('header');
        });
    });

    describe('resolveRecord()', function () {
        it('returns registered RecordLayout by name', function () {
            $record = simpleRecord('header');

            $layout = FileLayout::define('test')
                ->lineLength(10)
                ->addRecord($record)
                ->withSequence([Record::one($record)])
                ->build();

            $resolved = $layout->resolveRecord('header');
            expect($resolved->name)->toBe('header');
        });

        it('throws LayoutException for unregistered record name', function () {
            $record = simpleRecord('header');

            $layout = FileLayout::define('test')
                ->lineLength(10)
                ->addRecord($record)
                ->withSequence([Record::one($record)])
                ->build();

            expect(fn () => $layout->resolveRecord('nonexistent'))
                ->toThrow(LayoutException::class, 'nonexistent');
        });
    });

    describe('fromArray()', function () {
        it('succeeds with valid arguments', function () {
            $record = simpleRecord('header');

            $layout = FileLayout::fromArray(
                name:       'test',
                lineLength: 10,
                lineEnding: "\n",
                records:    ['header' => $record],
                sequence:   [Record::one($record)],
            );

            expect($layout->name)->toBe('test');
            expect($layout->getLineLength())->toBe(10);
        });

        it('applies the same validations as build() — lineLength zero', function () {
            $record = simpleRecord('header');

            expect(
                fn () => FileLayout::fromArray(
                    name:       'test',
                    lineLength: 0,
                    lineEnding: "\n",
                    records:    ['header' => $record],
                    sequence:   [Record::one($record)],
                )
            )->toThrow(LayoutException::class, 'lineLength');
        });

        it('applies the same validations as build() — empty sequence', function () {
            $record = simpleRecord('header');

            expect(
                fn () => FileLayout::fromArray(
                    name:       'test',
                    lineLength: 10,
                    lineEnding: "\n",
                    records:    ['header' => $record],
                    sequence:   [],
                )
            )->toThrow(LayoutException::class);
        });

        it('applies the same validations as build() — unregistered record in sequence', function () {
            $registered   = simpleRecord('registered');
            $unregistered = simpleRecord('unregistered');

            expect(
                fn () => FileLayout::fromArray(
                    name:       'test',
                    lineLength: 10,
                    lineEnding: "\n",
                    records:    ['registered' => $registered],
                    sequence:   [Record::one($unregistered)],
                )
            )->toThrow(LayoutException::class, 'unregistered');
        });
    });
});
