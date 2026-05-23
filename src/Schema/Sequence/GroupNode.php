<?php

namespace Husail\EdiSdk\Schema\Sequence;

/**
 * Node that represents a repeatable group of records.
 *
 * Created via Group::repeat(). The set of child nodes can occur N times.
 * The $identifyBy receives the raw line and returns the RecordLayout name or null.
 * When it returns null, the walker considers that the line does not belong to
 * the group and moves up one level in the tree (closes the group).
 *
 * Example — CNAB 240 repeatable batches:
 *   Group::repeat(
 *       identifyBy: fn(string $line): ?string => match($line[7]) {
 *           '1' => 'batch_header',
 *           '3' => 'detail',
 *           '5' => 'batch_trailer',
 *           default => null,
 *       },
 *       children: [
 *           Record::one($batchHeader),
 *           Group::ambiguous(...),
 *           Record::one($batchTrailer),
 *       ]
 *   )
 */
final readonly class GroupNode implements SequenceNode
{
    /**
     * @param SequenceNode[] $children
     */
    public function __construct(
        public array      $children,
        public IdentifyBy $identifyBy,
    ) {
    }

    public function recordNames(): array
    {
        return array_merge(
            ...array_map(fn (SequenceNode $n) => $n->recordNames(), $this->children)
        );
    }
}
