<?php

use Husail\EdiSdk\Edi;
use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Drivers\JsonDriver;
use Husail\EdiSdk\Drivers\YamlDriver;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Group;
use Husail\EdiSdk\Schema\Sequence\Record;

// Layout que simula um subconjunto do CNAB 240 — usado em todos os testes de integração
function cnabLikeLayout(): FileLayout
{
    $fileHeader = RecordLayout::define('file_header')
        ->lineLength(20)
        ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
        ->addField('record_type', pos: 4, len: 1, type: FieldType::NUMERIC, const: '0')
        ->addField('company_name', pos: 5, len: 16, type: FieldType::ALPHA)
        ->build();

    $batchHeader = RecordLayout::define('batch_header')
        ->lineLength(20)
        ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
        ->addField('batch_code', pos: 4, len: 4, type: FieldType::NUMERIC)
        ->addField('record_type', pos: 8, len: 1, type: FieldType::NUMERIC, const: '1')
        ->addField('form', pos: 9, len: 2, type: FieldType::NUMERIC)
        ->addField('filler', pos: 11, len: 10, type: FieldType::ALPHA, required: false)
        ->build();

    $segmentA = RecordLayout::define('segment_a')
        ->lineLength(20)
        ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
        ->addField('batch_code', pos: 4, len: 4, type: FieldType::NUMERIC)
        ->addField('record_type', pos: 8, len: 1, type: FieldType::NUMERIC, const: '3')
        ->addField('segment_code', pos: 9, len: 1, type: FieldType::ALPHA, const: 'A')
        ->addField('amount', pos: 10, len: 11, type: FieldType::NUMERIC, cast: 'float', decimalPlaces: 2)
        ->build();

    $batchTrailer = RecordLayout::define('batch_trailer')
        ->lineLength(20)
        ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
        ->addField('batch_code', pos: 4, len: 4, type: FieldType::NUMERIC)
        ->addField('record_type', pos: 8, len: 1, type: FieldType::NUMERIC, const: '5')
        ->addField('total_amount', pos: 9, len: 12, type: FieldType::NUMERIC, cast: 'float', decimalPlaces: 2)
        ->build();

    $fileTrailer = RecordLayout::define('file_trailer')
        ->lineLength(20)
        ->addField('bank_code', pos: 1, len: 3, type: FieldType::NUMERIC, const: '341')
        ->addField('record_type', pos: 4, len: 1, type: FieldType::NUMERIC, const: '9')
        ->addField('batch_count', pos: 5, len: 6, type: FieldType::NUMERIC, cast: 'int')
        ->addField('record_count', pos: 11, len: 6, type: FieldType::NUMERIC, cast: 'int')
        ->addField('filler', pos: 17, len: 4, type: FieldType::ALPHA, required: false)
        ->build();

    return FileLayout::define('cnab-like')
        ->lineLength(20)
        ->lineEnding("\r\n")
        ->addRecord($fileHeader)
        ->addRecord($batchHeader)
        ->addRecord($segmentA)
        ->addRecord($batchTrailer)
        ->addRecord($fileTrailer)
        ->withSequence([
            Record::one($fileHeader),
            Group::repeat(
                identifyBy: fn (string $line): ?string => match ($line[7]) {
                    '1' => 'batch_header',
                    '3' => 'segment_a',
                    '5' => 'batch_trailer',
                    default => null,
                },
                children: [
                    Record::one($batchHeader),
                    Group::ambiguous(
                        identifyBy: fn (string $line): ?string => $line[7] === '3' ? 'segment_a' : null,
                        children: [Record::many($segmentA)]
                    ),
                    Record::one($batchTrailer),
                ]
            ),
            Record::one($fileTrailer),
        ])
        ->build();
}

describe('Write → Parse roundtrip', function () {

    it('writes and parses back a single-batch file correctly', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'ACME LTDA'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 150.75])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 320.00])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 470.75])
            ->add('file_trailer', ['batch_count' => 1, 'record_count' => 6])
            ->toString();

        $result = Edi::parse($content, $layout);

        expect($result->count())->toBe(6);

        $header = $result->first('file_header');
        expect($header)->not->toBeNull();
        expect($header->get('company_name'))->toBe('ACME LTDA       ');
        expect($header->get('bank_code'))->toBe('341');

        $segments = $result->records('segment_a');
        expect($segments->count())->toBe(2);
        expect($segments->first()?->get('amount'))->toBe(150.75);
        expect($segments->last()?->get('amount'))->toBe(320.00);
        expect($segments->nth(1)?->get('amount'))->toBe(320.00);

        $trailer = $result->first('batch_trailer');
        expect($trailer?->get('total_amount'))->toBe(470.75);

        $fileTrailer = $result->first('file_trailer');
        expect($fileTrailer?->get('batch_count'))->toBe(1);
        expect($fileTrailer?->get('record_count'))->toBe(6);
    });

    it('writes and parses back a multi-batch file correctly', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'MULTI BATCH'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 100.00])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 100.00])
            ->add('batch_header', ['batch_code' => '0002', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0002', 'amount' => 200.00])
            ->add('segment_a', ['batch_code' => '0002', 'amount' => 300.00])
            ->add('batch_trailer', ['batch_code' => '0002', 'total_amount' => 500.00])
            ->add('file_trailer', ['batch_count' => 2, 'record_count' => 9])
            ->toString();

        $result = Edi::parse($content, $layout);

        expect($result->count())->toBe(9);
        expect($result->records('batch_header')->count())->toBe(2);
        expect($result->records('segment_a')->count())->toBe(3);
        expect($result->records('batch_trailer')->count())->toBe(2);

        $segments = $result->records('segment_a');
        expect($segments->nth(0)?->get('amount'))->toBe(100.00);
        expect($segments->nth(1)?->get('amount'))->toBe(200.00);
        expect($segments->nth(2)?->get('amount'))->toBe(300.00);
    });

    it('written file passes validation', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'ACME'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 99.99])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 99.99])
            ->add('file_trailer', ['batch_count' => 1, 'record_count' => 5])
            ->toString();

        $result = Edi::validate($content, $layout);

        expect($result->passes())->toBeTrue();
        expect($result->errors())->toBeEmpty();
    });

    it('get() returns default when field does not exist', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'ACME'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 10.00])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 10.00])
            ->add('file_trailer', ['batch_count' => 1, 'record_count' => 5])
            ->toString();

        $header = Edi::parse($content, $layout)->first('file_header');

        expect($header?->get('nonexistent_field', default: 'fallback'))->toBe('fallback');
        expect($header?->get('nonexistent_field'))->toBeNull();
    });

    it('filter() returns a subset of the collection', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'ACME'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 50.00])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 200.00])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 10.00])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 260.00])
            ->add('file_trailer', ['batch_count' => 1, 'record_count' => 7])
            ->toString();

        $highValue = Edi::parse($content, $layout)
            ->records('segment_a')
            ->filter(fn ($r) => $r->get('amount') > 100);

        expect($highValue->count())->toBe(1);
        expect($highValue->first()?->get('amount'))->toBe(200.00);
    });

    it('toArray() on collection maintains retrocompatibility', function () {
        $layout = cnabLikeLayout();

        $content = Edi::write($layout)
            ->add('file_header', ['company_name' => 'ACME'])
            ->add('batch_header', ['batch_code' => '0001', 'form' => '45'])
            ->add('segment_a', ['batch_code' => '0001', 'amount' => 99.00])
            ->add('batch_trailer', ['batch_code' => '0001', 'total_amount' => 99.00])
            ->add('file_trailer', ['batch_count' => 1, 'record_count' => 5])
            ->toString();

        $arr = Edi::parse($content, $layout)->records('segment_a')->toArray();

        expect($arr)->toHaveCount(1);
        expect($arr[0]['_record'])->toBe('segment_a');
        expect($arr[0]['amount'])->toBe(99.00);
    });
});

describe('Driver → Write → Parse roundtrip', function () {

    it('layout from JSON produces same output as PHP builder', function () {
        $json = json_encode([
            'name'        => 'simple',
            'line_length' => 10,
            'line_ending' => '\n',
            'records'     => [[
                'name'   => 'rec',
                'fields' => [
                    ['name' => 'type', 'pos' => 1, 'len' => 1, 'type' => 'alpha', 'const' => 'R'],
                    ['name' => 'code', 'pos' => 2, 'len' => 9, 'type' => 'numeric'],
                ],
            ]],
            'sequence' => [['type' => 'record', 'record' => 'rec']],
        ]);

        $layoutFromJson  = (new JsonDriver())->load($json);
        $contentFromJson = Edi::write($layoutFromJson)->add('rec', ['code' => 42])->toString();

        $recPhp = RecordLayout::define('rec')
            ->lineLength(10)
            ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'R')
            ->addField('code', pos: 2, len: 9, type: FieldType::NUMERIC)
            ->build();

        $layoutFromPhp = FileLayout::define('simple')
            ->lineLength(10)
            ->lineEnding("\n")
            ->addRecord($recPhp)
            ->withSequence([Record::one($recPhp)])
            ->build();

        $contentFromPhp = Edi::write($layoutFromPhp)->add('rec', ['code' => 42])->toString();

        expect($contentFromJson)->toBe($contentFromPhp);
    });

    it('layout from YAML produces same output as JSON', function () {
        // line_ending usa aspas simples — convenção do SDK para escape sequences.
        // Aspas duplas seriam interpretadas pelo symfony/yaml antes de chegar no parseLineEnding.
        $yaml = <<<'YAML'
name: simple
line_length: 10
line_ending: '\n'
records:
  - name: rec
    fields:
      - { name: type, pos: 1, len: 1, type: alpha, const: 'R' }
      - { name: code, pos: 2, len: 9, type: numeric }
sequence:
  - { type: record, record: rec }
YAML;

        $json = json_encode([
            'name'        => 'simple',
            'line_length' => 10,
            'line_ending' => '\n',
            'records'     => [[
                'name'   => 'rec',
                'fields' => [
                    ['name' => 'type', 'pos' => 1, 'len' => 1, 'type' => 'alpha', 'const' => 'R'],
                    ['name' => 'code', 'pos' => 2, 'len' => 9, 'type' => 'numeric'],
                ],
            ]],
            'sequence' => [['type' => 'record', 'record' => 'rec']],
        ]);

        $fromYaml = Edi::write((new YamlDriver())->load($yaml))->add('rec', ['code' => 7])->toString();
        $fromJson = Edi::write((new JsonDriver())->load($json))->add('rec', ['code' => 7])->toString();

        expect($fromYaml)->toBe($fromJson);
    });
});
