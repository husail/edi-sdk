<?php

namespace Husail\EdiSdk;

use Husail\EdiSdk\Engine\Writer;
use Husail\EdiSdk\Engine\TreeWalker;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Results\ParseResult;
use Husail\EdiSdk\Results\ValidationResult;
use Husail\EdiSdk\Engine\Visitors\ParserVisitor;
use Husail\EdiSdk\Engine\Visitors\ValidatorVisitor;

/**
 * Main SDK entry point for write, parse, and validate operations.
 */
final class Edi
{
    private function __construct()
    {
    }

    public static function write(FileLayout $layout): Writer
    {
        return new Writer($layout);
    }

    public static function parse(string $content, FileLayout $layout): ParseResult
    {
        $visitor = new ParserVisitor();
        (new TreeWalker($layout))->walk($content, $visitor);

        return $visitor->getResult();
    }

    public static function validate(string $content, FileLayout $layout): ValidationResult
    {
        $visitor = new ValidatorVisitor();
        (new TreeWalker($layout))->walk($content, $visitor);

        return $visitor->getResult();
    }
}
