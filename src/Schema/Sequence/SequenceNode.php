<?php

namespace Husail\EdiSdk\Schema\Sequence;

/**
 * Node in the sequence tree of a FileLayout.
 *
 * The sequence is a tree of nodes that describes the order and grouping
 * of records in a file. The TreeWalker traverses it to know what to expect
 * at each position of the file.
 */
interface SequenceNode
{
    /**
     * Names of the RecordLayouts this node represents or contains.
     * Used to validate that all referenced records are registered.
     *
     * @return string[]
     */
    public function recordNames(): array;
}
