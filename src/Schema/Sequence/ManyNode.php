<?php

namespace Husail\EdiSdk\Schema\Sequence;

use Husail\EdiSdk\Schema\RecordLayout;

/**
 * Leaf node that represents zero or more occurrences of a RecordLayout.
 *
 * Created via:
 *   Record::many($layout)
 */
final readonly class ManyNode implements SequenceNode
{
    public function __construct(
        public RecordLayout $layout,
    ) {
    }

    public function recordNames(): array
    {
        return [$this->layout->name];
    }
}
