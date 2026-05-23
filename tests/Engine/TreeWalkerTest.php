<?php

use Husail\EdiSdk\Edi;
use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Group;
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Exceptions\ParseException;

// =========================================================================
// Helpers locais
// =========================================================================

/**
 * Layout simples: file_header, N details, file_trailer.
 * Linha de 10 chars, identificação por pos 1.
 */
function walkerFlatLayout(): FileLayout
{
    $header = RecordLayout::define('file_header')
        ->lineLength(10)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'H')
        ->addField('name', pos: 2, len: 9, type: FieldType::ALPHA)
        ->build();

    $detail = RecordLayout::define('detail')
        ->lineLength(10)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'D')
        ->addField('value', pos: 2, len: 9, type: FieldType::NUMERIC, cast: 'int')
        ->build();

    $trailer = RecordLayout::define('file_trailer')
        ->lineLength(10)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'T')
        ->addField('count', pos: 2, len: 9, type: FieldType::NUMERIC, cast: 'int')
        ->build();

    return FileLayout::define('flat')
        ->lineLength(10)
        ->lineEnding("\n")
        ->addRecord($header)
        ->addRecord($detail)
        ->addRecord($trailer)
        ->withSequence([
            Record::one($header),
            Group::repeat(
                identifyBy: fn (string $line): ?string => $line[0] === 'D' ? 'detail' : null,
                children: [Record::many($detail)]
            ),
            Record::one($trailer),
        ])
        ->build();
}

/**
 * Layout com ambiguous composto simulando B vs B PIX e J vs J52.
 * Linha de 20 chars.
 *
 * Mapa de posições (1-based):
 *   pos 1-4  = batch_code
 *   pos 5    = record_type ('1'=batch_header, '3'=detail, '5'=batch_trailer)
 *   pos 6-9  = filler
 *   pos 9    = segment (A, B, J) — GroupNode pai usa pos 5 para tipo de registro
 *   pos 10   = pos 9 (0-based 8) — segment code lido pelo AmbiguousNode
 *   pos 10-11 = subtipo B PIX (01-04)
 *   pos 10-13 = subtipo J52 ('  52')
 *
 * Para simplificar os dados de teste, usamos este mapeamento:
 *   byte 0-3  = batch_code (ex: '0001')
 *   byte 4    = record_type ('1', '3', '5')
 *   byte 5-7  = filler ('   ')
 *   byte 8    = segment_code ('A', 'B', 'J', 'H', 'T')
 *   byte 9-10 = subtipo (pix_key_type ou j52 mark)
 *   byte 11-19 = dados
 */
function walkerAmbiguousLayout(): FileLayout
{
    // Todas as linhas têm 20 chars
    // GroupNode pai: identifica pelo byte 4 ('1'=batch_header, '3'=detail, '5'=batch_trailer)
    // AmbiguousNode: identifica pelo byte 8 (segment_code) e bytes 9-10 (subtipo)

    $makeRecord = fn (string $name, string $segConst) => RecordLayout::define($name)
        ->lineLength(20)
        ->addField('batch_code', pos: 1, len: 4, type: FieldType::ALPHA)
        ->addField('record_type', pos: 5, len: 1, type: FieldType::ALPHA)
        ->addField('filler_1', pos: 6, len: 3, type: FieldType::ALPHA, required: false)
        ->addField('segment_code', pos: 9, len: 1, type: FieldType::ALPHA, const: $segConst)
        ->addField('subtype', pos: 10, len: 2, type: FieldType::ALPHA, required: false)
        ->addField('data', pos: 12, len: 9, type: FieldType::ALPHA, required: false)
        ->build();

    $segA    = $makeRecord('segment_a', 'A');
    $segB    = $makeRecord('segment_b', 'B');
    $segBPix = $makeRecord('segment_b_pix', 'B');
    $segJ    = $makeRecord('segment_j', 'J');
    $segJ52  = $makeRecord('segment_j52', 'J');

    $batchHeader = RecordLayout::define('batch_header')
        ->lineLength(20)
        ->addField('batch_code', pos: 1, len: 4, type: FieldType::ALPHA)
        ->addField('record_type', pos: 5, len: 1, type: FieldType::ALPHA, const: '1')
        ->addField('filler', pos: 6, len: 15, type: FieldType::ALPHA, required: false)
        ->build();

    $batchTrailer = RecordLayout::define('batch_trailer')
        ->lineLength(20)
        ->addField('batch_code', pos: 1, len: 4, type: FieldType::ALPHA)
        ->addField('record_type', pos: 5, len: 1, type: FieldType::ALPHA, const: '5')
        ->addField('filler', pos: 6, len: 15, type: FieldType::ALPHA, required: false)
        ->build();

    $fileHeader = RecordLayout::define('file_header')
        ->lineLength(20)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'H')
        ->addField('filler', pos: 2, len: 19, type: FieldType::ALPHA, required: false)
        ->build();

    $fileTrailer = RecordLayout::define('file_trailer')
        ->lineLength(20)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'T')
        ->addField('filler', pos: 2, len: 19, type: FieldType::ALPHA, required: false)
        ->build();

    // identifyBy composto — byte 8 = segment_code, bytes 9-10 = subtipo
    // Ordem: mais específico primeiro
    $segmentIdentifyBy = function (string $line): ?string {
        $seg     = $line[8]  ?? '';
        $subtype = substr($line, 9, 2);

        return match (true) {
            $seg === 'B' && in_array($subtype, ['01', '02', '03', '04'], true) => 'segment_b_pix',
            $seg === 'B'                                                        => 'segment_b',
            $seg === 'J' && $subtype === '52'                                  => 'segment_j52',
            $seg === 'J'                                                        => 'segment_j',
            $seg === 'A'                                                        => 'segment_a',
            default                                                             => null,
        };
    };

    return FileLayout::define('cnab-ambiguous')
        ->lineLength(20)
        ->lineEnding("\n")
        ->addRecord($fileHeader)
        ->addRecord($batchHeader)
        ->addRecord($segA)
        ->addRecord($segB)
        ->addRecord($segBPix)
        ->addRecord($segJ)
        ->addRecord($segJ52)
        ->addRecord($batchTrailer)
        ->addRecord($fileTrailer)
        ->withSequence([
            Record::one($fileHeader),
            Group::repeat(
                identifyBy: fn (string $line): ?string => match ($line[4] ?? '') {
                    '1' => 'batch_header',
                    '3' => 'detail',
                    '5' => 'batch_trailer',
                    default => null,
                },
                children: [
                    Record::one($batchHeader),
                    Group::ambiguous(
                        identifyBy: $segmentIdentifyBy,
                        children: [
                            Record::many($segA),
                            Record::many($segB),
                            Record::many($segBPix),
                            Record::many($segJ),
                            Record::many($segJ52),
                        ]
                    ),
                    Record::one($batchTrailer),
                ]
            ),
            Record::one($fileTrailer),
        ])
        ->build();
}

/**
 * Constrói uma linha de 20 chars com os campos nas posições corretas.
 *
 * Layout:
 *   pos 1-4  (idx 0-3)  = batch_code
 *   pos 5    (idx 4)    = record_type
 *   pos 6-8  (idx 5-7)  = filler
 *   pos 9    (idx 8)    = segment_code
 *   pos 10-11 (idx 9-10) = subtype
 *   pos 12-20 (idx 11-19) = data
 */
function makeLine(
    string $batchCode,
    string $recordType,
    string $segmentCode = ' ',
    string $subtype = '  ',
    string $data = '         '
): string {
    return str_pad(
        $batchCode . $recordType . '   ' . $segmentCode . $subtype . $data,
        20
    );
}

// =========================================================================
// Testes
// =========================================================================

describe('TreeWalker', function () {

    describe('flat sequence', function () {
        it('parses header, details and trailer', function () {
            $content = "HACME CORP\nD000000100\nD000000200\nT000000002\n";

            $result = Edi::parse($content, walkerFlatLayout());

            expect($result->count())->toBe(4);
            expect($result->first('file_header')?->get('name'))->toBe('ACME CORP');
            expect($result->records('detail')->count())->toBe(2);
            expect($result->records('detail')->first()?->get('value'))->toBe(100);
            expect($result->records('detail')->last()?->get('value'))->toBe(200);
            expect($result->first('file_trailer')?->get('count'))->toBe(2);
        });

        it('parses file with no detail lines', function () {
            $content = "HACME CORP\nT000000000\n";

            $result = Edi::parse($content, walkerFlatLayout());

            expect($result->count())->toBe(2);
            expect($result->first('file_header'))->not->toBeNull();
            expect($result->first('file_trailer'))->not->toBeNull();
        });

        it('handles CRLF line endings', function () {
            $content = "HACME CORP\r\nD000000001\r\nT000000001\r\n";

            $result = Edi::parse($content, walkerFlatLayout());

            expect($result->count())->toBe(3);
        });

        it('throws ParseException when required record is missing', function () {
            $content = "HACME CORP\nD000000001\n";

            expect(fn () => Edi::parse($content, walkerFlatLayout()))
                ->toThrow(ParseException::class, 'file_trailer');
        });
    });

    describe('AmbiguousNode — B vs B PIX', function () {
        it('routes B with pix subtype to segment_b_pix', function () {
            // byte 8 = 'B', bytes 9-10 = '01' → segment_b_pix
            $content = implode("\n", [
                str_pad('H', 20),                           // file_header
                makeLine('0001', '1'),                      // batch_header
                makeLine('0001', '3', 'B', '01', 'PIX KEY  '), // segment_b_pix
                makeLine('0001', '3', 'B', '  ', 'ADDRESS  '), // segment_b
                makeLine('0001', '5'),                      // batch_trailer
                str_pad('T', 20),                           // file_trailer
            ]) . "\n";

            $result = Edi::parse($content, walkerAmbiguousLayout());

            expect($result->records('segment_b_pix')->count())->toBe(1);
            expect($result->records('segment_b')->count())->toBe(1);
        });

        it('routes B without pix subtype to segment_b', function () {
            $content = implode("\n", [
                str_pad('H', 20),
                makeLine('0001', '1'),
                makeLine('0001', '3', 'B', '  ', 'ADDRESS  '),
                makeLine('0001', '5'),
                str_pad('T', 20),
            ]) . "\n";

            $result = Edi::parse($content, walkerAmbiguousLayout());

            expect($result->records('segment_b')->count())->toBe(1);
            expect($result->records('segment_b_pix')->count())->toBe(0);
        });
    });

    describe('AmbiguousNode — J vs J52', function () {
        it('routes J with mark 52 to segment_j52', function () {
            $content = implode("\n", [
                str_pad('H', 20),
                makeLine('0001', '1'),
                makeLine('0001', '3', 'J', '52', 'DATA     '), // segment_j52
                makeLine('0001', '3', 'J', '  ', 'BOLETO   '), // segment_j
                makeLine('0001', '5'),
                str_pad('T', 20),
            ]) . "\n";

            $result = Edi::parse($content, walkerAmbiguousLayout());

            expect($result->records('segment_j52')->count())->toBe(1);
            expect($result->records('segment_j')->count())->toBe(1);
        });

        it('routes J without mark 52 to segment_j', function () {
            $content = implode("\n", [
                str_pad('H', 20),
                makeLine('0001', '1'),
                makeLine('0001', '3', 'J', '  ', 'BOLETO   '),
                makeLine('0001', '5'),
                str_pad('T', 20),
            ]) . "\n";

            $result = Edi::parse($content, walkerAmbiguousLayout());

            expect($result->records('segment_j')->count())->toBe(1);
            expect($result->records('segment_j52')->count())->toBe(0);
        });
    });

    describe('AmbiguousNode — mixed segments', function () {
        it('correctly separates A, B PIX, B, J52 and J in the same batch', function () {
            $content = implode("\n", [
                str_pad('H', 20),
                makeLine('0001', '1'),
                makeLine('0001', '3', 'A', '  ', 'DATA     '),
                makeLine('0001', '3', 'B', '02', 'PIX KEY  '),
                makeLine('0001', '3', 'B', '  ', 'ADDRESS  '),
                makeLine('0001', '3', 'J', '52', 'DATA     '),
                makeLine('0001', '3', 'J', '  ', 'BOLETO   '),
                makeLine('0001', '5'),
                str_pad('T', 20),
            ]) . "\n";

            $result = Edi::parse($content, walkerAmbiguousLayout());

            expect($result->records('segment_a')->count())->toBe(1);
            expect($result->records('segment_b_pix')->count())->toBe(1);
            expect($result->records('segment_b')->count())->toBe(1);
            expect($result->records('segment_j52')->count())->toBe(1);
            expect($result->records('segment_j')->count())->toBe(1);
        });
    });

    describe('Validator via TreeWalker', function () {
        it('passes for a valid flat file', function () {
            $content = "HACME CORP\nD000000001\nT000000001\n";

            $result = Edi::validate($content, walkerFlatLayout());

            expect($result->passes())->toBeTrue();
        });

        it('fails when line length is wrong', function () {
            $content = "HACME\nT000000000\n";

            $result = Edi::validate($content, walkerFlatLayout());

            expect($result->fails())->toBeTrue();
            expect($result->errorsForLine(1))->not->toBeEmpty();
        });

        it('fails when const field has wrong value', function () {
            $content = "XACME CORP\nT000000000\n";

            $result = Edi::validate($content, walkerFlatLayout());

            expect($result->fails())->toBeTrue();
            expect($result->errorsForLine(1)[0]->field)->toBe('type');
        });
    });
});
