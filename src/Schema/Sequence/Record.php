<?php

namespace Husail\EdiSdk\Schema\Sequence;

use Husail\EdiSdk\Schema\RecordLayout;

/**
 * Static factory for record nodes in the FileLayout sequence.
 *
 * Usage:
 *   Record::one($fileHeader) → required, one occurrence
 *   Record::optional($segmentB) → optional, one occurrence
 *   Record::many($segmentA) → zero or more occurrences
 */
final class Record
{
    private function __construct()
    {
    }

    public static function one(RecordLayout $layout): RecordNode
    {
        return new RecordNode(layout: $layout, required: true);
    }

    public static function optional(RecordLayout $layout): RecordNode
    {
        return new RecordNode(layout: $layout, required: false);
    }

    public static function many(RecordLayout $layout): ManyNode
    {
        return new ManyNode(layout: $layout);
    }
}
