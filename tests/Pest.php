<?php

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Group;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Schema\Sequence\Record;

// Helper global: cria um RecordLayout simples de 10 chars para testes
function simpleRecord(string $name, int $lineLength = 10): RecordLayout
{
    return RecordLayout::define($name)
        ->lineLength($lineLength)
        ->addField('type', pos: 1, len: 1, type: FieldType::ALPHA)
        ->addField('data', pos: 2, len: $lineLength - 1, type: FieldType::ALPHA)
        ->build();
}

// Helper global: cria um FieldDefinition simples
function simpleField(
    string      $name = 'field',
    int         $pos = 1,
    int         $len = 10,
    FieldType   $type = FieldType::ALPHA,
    ?string     $const = null,
    bool        $required = true,
    ?string     $cast = null,
    int         $decimalPlaces = 0,
    ?string     $format = null,
): FieldDefinition {
    return new FieldDefinition(
        name:          $name,
        position:      $pos,
        length:        $len,
        type:          $type,
        const:         $const,
        required:      $required,
        cast:          $cast,
        decimalPlaces: $decimalPlaces,
        format:        $format,
    );
}
