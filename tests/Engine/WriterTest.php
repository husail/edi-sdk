<?php

use Husail\EdiSdk\Edi;
use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Exceptions\LayoutException;

// Helper: layout simples de 10 chars com header e trailer
function twoRecordLayout(): FileLayout
{
    $header = RecordLayout::define('header')
        ->lineLength(10)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'H')
        ->addField('data', pos: 2, len: 9, type: FieldType::ALPHA)
        ->build();

    $trailer = RecordLayout::define('trailer')
        ->lineLength(10)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'T')
        ->addField('count', pos: 2, len: 9, type: FieldType::NUMERIC)
        ->build();

    return FileLayout::define('test')
        ->lineLength(10)
        ->lineEnding("\n")
        ->addRecord($header)
        ->addRecord($trailer)
        ->withSequence([Record::one($header), Record::one($trailer)])
        ->build();
}

describe('Writer', function () {

    describe('toString()', function () {
        it('generates correct output for a simple two-record file', function () {
            $layout = twoRecordLayout();

            $content = Edi::write($layout)
                ->add('header', ['data' => 'ACME'])
                ->add('trailer', ['count' => 1])
                ->toString();

            $lines = explode("\n", rtrim($content));

            expect($lines)->toHaveCount(2);
            expect($lines[0])->toBe('HACME     ');
            expect($lines[1])->toBe('T000000001');
        });

        it('uses line ending defined in layout', function () {
            $record = simpleRecord('rec');

            $layout = FileLayout::define('test')
                ->lineLength(10)
                ->lineEnding("\r\n")
                ->addRecord($record)
                ->withSequence([Record::one($record)])
                ->build();

            $content = Edi::write($layout)
                ->add('rec', ['type' => 'A', 'data' => 'test'])
                ->toString();

            expect($content)->toContain("\r\n");
        });

        it('const fields ignore provided value', function () {
            $layout = twoRecordLayout();

            $content = Edi::write($layout)
                ->add('header', ['type' => 'X', 'data' => 'test']) // type deve ser 'H'
                ->add('trailer', ['count' => 0])
                ->toString();

            expect(explode("\n", rtrim($content))[0][0])->toBe('H');
        });

        it('null fields use padding', function () {
            $layout = twoRecordLayout();

            $content = Edi::write($layout)
                ->add('header', ['data' => null])
                ->add('trailer', ['count' => null])
                ->toString();

            $lines = explode("\n", rtrim($content));
            expect($lines[0])->toBe('H         ');  // H + 9 espaços
            expect($lines[1])->toBe('T000000000');  // T + 9 zeros
        });

        it('adds multiple detail lines', function () {
            $detail = RecordLayout::define('detail')
                ->lineLength(10)
                ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA, const: 'D')
                ->addField('code', pos: 2, len: 9, type: FieldType::NUMERIC)
                ->build();

            $layout = FileLayout::define('test')
                ->lineLength(10)
                ->lineEnding("\n")
                ->addRecord($detail)
                ->withSequence([Record::many($detail)])
                ->build();

            $content = Edi::write($layout)
                ->add('detail', ['code' => 1])
                ->add('detail', ['code' => 2])
                ->add('detail', ['code' => 3])
                ->toString();

            $lines = explode("\n", rtrim($content));
            expect($lines)->toHaveCount(3);
            expect($lines[0])->toBe('D000000001');
            expect($lines[1])->toBe('D000000002');
            expect($lines[2])->toBe('D000000003');
        });
    });

    describe('toFile()', function () {
        it('writes content to file', function () {
            $path   = sys_get_temp_dir() . '/edi_test_' . uniqid() . '.txt';
            $layout = twoRecordLayout();

            Edi::write($layout)
                ->add('header', ['data' => 'FILE'])
                ->add('trailer', ['count' => 1])
                ->toFile($path);

            expect(file_exists($path))->toBeTrue();
            expect(file_get_contents($path))->toContain('HFILE');

            unlink($path);
        });
    });

    describe('add() errors', function () {
        it('throws LayoutException when record name does not exist', function () {
            $layout = twoRecordLayout();

            expect(
                fn ()
                => Edi::write($layout)->add('nonexistent', [])
            )->toThrow(LayoutException::class);
        });
    });
});
