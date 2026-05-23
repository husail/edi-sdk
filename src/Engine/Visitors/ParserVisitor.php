<?php

namespace Husail\EdiSdk\Engine\Visitors;

use Husail\EdiSdk\Results\ParseResult;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Results\ParsedRecord;
use Husail\EdiSdk\Engine\FieldSerializer;

/**
 * Visitor that deserializes each line and accumulates the records.
 *
 * Used by TreeWalker to implement Edi::parse().
 * Each visited line becomes a ParsedRecord with the deserialized fields.
 */
final class ParserVisitor implements LineVisitor
{
    /** @var ParsedRecord[] */
    private array $records = [];

    public function visit(string $line, RecordLayout $layout, int $lineNumber): void
    {
        $fields = [];

        foreach ($layout->getFields() as $field) {
            $fields[$field->name] = FieldSerializer::deserialize($line, $field);
        }

        $this->records[] = new ParsedRecord(
            record: $layout->name,
            fields: $fields,
        );
    }

    public function getResult(): ParseResult
    {
        return new ParseResult($this->records);
    }
}
