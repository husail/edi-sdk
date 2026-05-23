<?php

namespace Husail\EdiSdk\Schema\Sequence;

use Husail\EdiSdk\Schema\RecordLayout;

/**
 * Leaf node that represents a single occurrence of a RecordLayout.
 *
 * Created via:
 *   Record::one($layout) → required
 *   Record::optional($layout) → optional
 */
final readonly class RecordNode implements SequenceNode
{
    public function __construct(
        public RecordLayout $layout,
        public bool         $required = true,
    ) {
    }

    public function recordNames(): array
    {
        return [$this->layout->name];
    }
}
