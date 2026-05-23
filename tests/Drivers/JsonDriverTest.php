<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Drivers\JsonDriver;
use Husail\EdiSdk\Schema\Sequence\GroupNode;
use Husail\EdiSdk\Exceptions\LayoutException;
use Husail\EdiSdk\Schema\Sequence\IdentifyBy;
use Husail\EdiSdk\Schema\Sequence\RecordNode;

function minimalJson(array $overrides = []): string
{
    $base = [
        'name'        => 'test',
        'line_length' => 10,
        'line_ending' => '\n',
        'records'     => [
            [
                'name'   => 'header',
                'fields' => [
                    ['name' => 'type', 'pos' => 1, 'len' => 1, 'type' => 'alpha', 'const' => 'H'],
                    ['name' => 'data', 'pos' => 2, 'len' => 9, 'type' => 'alpha'],
                ],
            ],
        ],
        'sequence' => [
            ['type' => 'record', 'record' => 'header', 'required' => true],
        ],
    ];

    return json_encode(array_merge($base, $overrides));
}

describe('JsonDriver', function () {

    describe('load() from string', function () {
        it('loads a valid JSON layout from string', function () {
            $layout = (new JsonDriver())->load(minimalJson());

            expect($layout)->toBeInstanceOf(FileLayout::class)
                ->and($layout->name)->toBe('test')
                ->and($layout->getLineLength())->toBe(10);
        });

        it('registers all records from JSON', function () {
            $layout = (new JsonDriver())->load(minimalJson());

            expect($layout->getRecords())->toHaveKey('header');
        });

        it('maps field properties correctly', function () {
            $layout = (new JsonDriver())->load(minimalJson());
            $field  = $layout->resolveRecord('header')->getField('type');

            expect($field->name)->toBe('type')
                ->and($field->position)->toBe(1)
                ->and($field->length)->toBe(1)
                ->and($field->type)->toBe(FieldType::ALPHA)
                ->and($field->const)->toBe('H');
        });

        it('parses line_ending escape sequences', function () {
            $json   = minimalJson(['line_ending' => '\r\n']);
            $layout = (new JsonDriver())->load($json);

            expect($layout->getLineEnding())->toBe("\r\n");
        });

        it('builds sequence with RecordNode', function () {
            $layout   = (new JsonDriver())->load(minimalJson());
            $sequence = $layout->getSequence();

            expect($sequence)->toHaveCount(1)
                ->and($sequence[0])->toBeInstanceOf(RecordNode::class)
                ->and($sequence[0]->required)->toBeTrue();
        });

        it('builds sequence with GroupNode and IdentifyBy', function () {
            $json = json_encode([
                'name'        => 'grouped',
                'line_length' => 5,
                'line_ending' => '\n',
                'records'     => [
                    [
                        'name'   => 'header',
                        'fields' => [
                            ['name' => 'type', 'pos' => 1, 'len' => 1, 'type' => 'alpha', 'const' => 'H'],
                            ['name' => 'data', 'pos' => 2, 'len' => 4, 'type' => 'alpha'],
                        ],
                    ],
                    [
                        'name'   => 'detail',
                        'fields' => [
                            ['name' => 'type', 'pos' => 1, 'len' => 1, 'type' => 'alpha', 'const' => 'D'],
                            ['name' => 'code', 'pos' => 2, 'len' => 4, 'type' => 'numeric'],
                        ],
                    ],
                ],
                'sequence' => [
                    ['type' => 'record', 'record' => 'header', 'required' => true, 'identifier_value' => 'H'],
                    [
                        'type'                 => 'group',
                        'identify_by_position' => 1,
                        'children'             => [
                            ['type' => 'many', 'record' => 'detail', 'identifier_value' => 'D'],
                        ],
                    ],
                ],
            ]);

            $layout   = (new JsonDriver())->load($json);
            $sequence = $layout->getSequence();

            expect($sequence)->toHaveCount(2)
                ->and($sequence[1])->toBeInstanceOf(GroupNode::class)
                ->and($sequence[1]->identifyBy)->toBeInstanceOf(IdentifyBy::class);

            // identifyBy é invokable e funciona corretamente
            $identifyBy = $sequence[1]->identifyBy;
            expect($identifyBy('D0001'))->toBe('detail')
                ->and($identifyBy('X0001'))->toBeNull();
        });

        it('maps optional field properties: cast, decimal_places, format', function () {
            $json = json_encode([
                'name'        => 'test',
                'line_length' => 20,
                'line_ending' => '\n',
                'records'     => [[
                    'name'   => 'rec',
                    'fields' => [
                        ['name' => 'amount', 'pos' => 1,  'len' => 15, 'type' => 'numeric',
                            'cast' => 'float', 'decimal_places' => 2],
                        ['name' => 'date',   'pos' => 16, 'len' => 5,  'type' => 'alpha',
                            'cast' => 'date',  'format' => 'dmY'],
                    ],
                ]],
                'sequence' => [['type' => 'record', 'record' => 'rec']],
            ]);

            $layout = (new JsonDriver())->load($json);
            $amount = $layout->resolveRecord('rec')->getField('amount');
            $date   = $layout->resolveRecord('rec')->getField('date');

            expect($amount->cast)->toBe('float')
                ->and($amount->decimalPlaces)->toBe(2)
                ->and($date->cast)->toBe('date')
                ->and($date->format)->toBe('dmY');
        });
    });

    describe('load() from file', function () {
        it('loads a valid JSON layout from file path', function () {
            $path = sys_get_temp_dir() . '/edi_test_' . uniqid() . '.json';
            file_put_contents($path, minimalJson());

            $layout = (new JsonDriver())->load($path);

            expect($layout->name)->toBe('test');

            unlink($path);
        });
    });

    describe('error handling', function () {
        it('throws LayoutException for invalid JSON', function () {
            expect(fn () => (new JsonDriver())->load('{invalid json}'))
                ->toThrow(LayoutException::class);
        });

        it('throws LayoutException when name is missing', function () {
            $json = json_encode([
                'line_length' => 10,
                'line_ending' => '\n',
                'records'     => [],
                'sequence'    => [],
            ]);

            expect(fn () => (new JsonDriver())->load($json))
                ->toThrow(LayoutException::class, 'name');
        });

        it('throws LayoutException when line_length is missing', function () {
            $json = json_encode([
                'name'     => 'test',
                'records'  => [],
                'sequence' => [],
            ]);

            expect(fn () => (new JsonDriver())->load($json))
                ->toThrow(LayoutException::class, 'line_length');
        });

        it('throws LayoutException when field type is invalid', function () {
            $json = json_encode([
                'name'        => 'test',
                'line_length' => 10,
                'line_ending' => '\n',
                'records'     => [[
                    'name'   => 'rec',
                    'fields' => [['name' => 'f', 'pos' => 1, 'len' => 5, 'type' => 'invalid_type']],
                ]],
                'sequence' => [],
            ]);

            expect(fn () => (new JsonDriver())->load($json))
                ->toThrow(\ValueError::class); // FieldType::from() lança ValueError
        });

        it('throws LayoutException for unknown sequence node type', function () {
            $json = json_encode([
                'name'        => 'test',
                'line_length' => 10,
                'line_ending' => '\n',
                'records'     => [[
                    'name'   => 'rec',
                    'fields' => [['name' => 'f', 'pos' => 1, 'len' => 10, 'type' => 'alpha']],
                ]],
                'sequence' => [['type' => 'unknown_type', 'record' => 'rec']],
            ]);

            expect(fn () => (new JsonDriver())->load($json))
                ->toThrow(LayoutException::class, 'unknown_type');
        });
    });
});
