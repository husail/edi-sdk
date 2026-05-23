<?php

namespace Husail\EdiSdk\Schema\Sequence;

/**
 * Node that represents different interleaved record types at the same position.
 *
 * Created via Group::ambiguous(). The $identifyBy examines each line and decides
 * which RecordLayout it belongs to. When it returns null or an unknown name,
 * the walker considers the line does not belong to this node and moves up one level.
 *
 * Example — CNAB 240 interleaved segments A/B/J:
 *   Group::ambiguous(
 *       identifyBy: fn(string $line): ?string => match($line[13]) {
 *           'A' => 'segment_a',
 *           'B' => 'segment_b',
 *           'J' => 'segment_j',
 *           default => null,
 *       },
 *       children: [
 *           Record::many($segmentA),
 *           Record::optional($segmentB),
 *           Record::many($segmentJ),
 *       ]
 *   )
 */
final readonly class AmbiguousNode implements SequenceNode
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
