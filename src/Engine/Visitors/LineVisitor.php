<?php

namespace Husail\EdiSdk\Engine\Visitors;

use Husail\EdiSdk\Schema\RecordLayout;

/**
 * Contract for TreeWalker visitors.
 *
 * The TreeWalker navigates the sequence tree and calls visit() for each
 * consumed line. The visitor decides what to do with the line — deserialize,
 * validate, inspect, etc.
 *
 * Implementations:
 *   ParserVisitor — deserializes fields and accumulates ParsedRecord objects
 *   ValidatorVisitor — validates fields and accumulates errors
 */
interface LineVisitor
{
    /**
     * Processes a line from the file.
     *
     * @param string $line Raw line (without line ending).
     * @param RecordLayout $layout Layout of the record identified for this line.
     * @param int $lineNumber Line number (1-based).
     */
    public function visit(string $line, RecordLayout $layout, int $lineNumber): void;
}
