<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Drivers\YamlDriver;
use Husail\EdiSdk\Exceptions\LayoutException;

function minimalYaml(): string
{
    return <<<YAML
name: test
line_length: 10
line_ending: "\\n"
records:
  - name: header
    fields:
      - { name: type, pos: 1, len: 1, type: alpha, const: "H" }
      - { name: data, pos: 2, len: 9, type: alpha }
sequence:
  - { type: record, record: header, required: true }
YAML;
}

describe('YamlDriver', function () {

    describe('load() from string', function () {
        it('loads a valid YAML layout from string', function () {
            $layout = (new YamlDriver())->load(minimalYaml());

            expect($layout)->toBeInstanceOf(FileLayout::class);
            expect($layout->name)->toBe('test');
            expect($layout->getLineLength())->toBe(10);
        });

        it('registers all records from YAML', function () {
            $layout = (new YamlDriver())->load(minimalYaml());

            expect($layout->getRecords())->toHaveKey('header');
        });

        it('maps field properties correctly', function () {
            $layout = (new YamlDriver())->load(minimalYaml());
            $field  = $layout->resolveRecord('header')->getField('type');

            expect($field->name)->toBe('type');
            expect($field->position)->toBe(1);
            expect($field->length)->toBe(1);
            expect($field->type)->toBe(FieldType::ALPHA);
            expect($field->const)->toBe('H');
        });

        it('parses a more complete YAML with group sequence', function () {
            $yaml = <<<YAML
name: grouped
line_length: 5
line_ending: "\\n"
records:
  - name: header
    fields:
      - { name: type, pos: 1, len: 1, type: alpha, const: H }
      - { name: data, pos: 2, len: 4, type: alpha }
  - name: detail
    fields:
      - { name: type, pos: 1, len: 1, type: alpha, const: D }
      - { name: code, pos: 2, len: 4, type: numeric }
sequence:
  - type: record
    record: header
    required: true
  - type: group
    identify_by_position: 1
    children:
      - { type: many, record: detail, identifier_value: D }
YAML;

            $layout = (new YamlDriver())->load($yaml);

            expect($layout->getRecords())->toHaveKey('header');
            expect($layout->getRecords())->toHaveKey('detail');
            expect($layout->getSequence())->toHaveCount(2);
        });
    });

    describe('load() from file', function () {
        it('loads a valid YAML layout from file path', function () {
            $path = sys_get_temp_dir() . '/edi_test_' . uniqid() . '.yaml';
            file_put_contents($path, minimalYaml());

            $layout = (new YamlDriver())->load($path);

            expect($layout->name)->toBe('test');

            unlink($path);
        });
    });

    describe('error handling', function () {
        it('throws LayoutException for invalid YAML', function () {
            expect(fn () => (new YamlDriver())->load(":\tinvalid: yaml:\n  -"))
                ->toThrow(LayoutException::class);
        });

        it('throws LayoutException when YAML root is not a mapping', function () {
            expect(fn () => (new YamlDriver())->load("- item1\n- item2\n"))
                ->toThrow(LayoutException::class);
        });
    });
});
