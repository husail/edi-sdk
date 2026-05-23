<?php

namespace Husail\EdiSdk\Schema\Sequence;

/**
 * Static factory for grouping nodes in the FileLayout sequence.
 *
 * Usage:
 *   Group::repeat(identifyBy: fn($line) => ..., children: [...])
 *   Group::ambiguous(identifyBy: fn($line) => ..., children: [...])
 */
final class Group
{
    private function __construct()
    {
    }

    /**
     * Repeatable group: the set of child records can occur N times.
     *
     * The $identifyBy receives the raw line and returns the RecordLayout name
     * or null to close the group.
     *
     * @param \Closure(string): ?string $identifyBy
     * @param SequenceNode[] $children
     */
    public static function repeat(\Closure $identifyBy, array $children): GroupNode
    {
        return new GroupNode(
            children:   $children,
            identifyBy: IdentifyBy::using($identifyBy),
        );
    }

    /**
     * Ambiguous group: multiple possible record types at the same position.
     *
     * The $identifyBy examines each line and decides which RecordLayout it belongs to,
     * or returns null to close the node.
     *
     * @param \Closure(string): ?string $identifyBy
     * @param SequenceNode[] $children
     */
    public static function ambiguous(\Closure $identifyBy, array $children): AmbiguousNode
    {
        return new AmbiguousNode(
            children:   $children,
            identifyBy: IdentifyBy::using($identifyBy),
        );
    }
}
