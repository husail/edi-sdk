<?php

namespace Husail\EdiSdk\Schema\Sequence;

/**
 * Value object wrapping line-identification callables.
 *
 * Callbacks return a RecordLayout name when the line belongs to the current
 * node scope, or null to close the current node/group.
 */
final readonly class IdentifyBy
{
    private function __construct(private \Closure $fn)
    {
    }

    /** Named constructor for the PHP builder. Accepts a typed Closure. */
    public static function using(\Closure $fn): self
    {
        return new self($fn);
    }

    /** Named constructor for drivers. Converts any callable to a Closure. */
    public static function fromCallable(callable $fn): self
    {
        return new self(\Closure::fromCallable($fn));
    }

    public function __invoke(string $line): ?string
    {
        return ($this->fn)($line);
    }

    /**
     * Exposes the internal Closure for use as a $stopWhen predicate in TreeWalker.
     */
    public function toClosure(): \Closure
    {
        return $this->fn;
    }
}
