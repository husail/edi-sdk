<?php

namespace Husail\EdiSdk\Results;

/**
 * Typed collection of ParsedRecord objects returned by ParseResult::records().
 *
 * Allows fluent navigation over a set of records of the same type.
 *
 * Usage:
 *   $segments = $result->records('segment_a');
 *
 *   $segments->count();
 *   $segments->first()?->get('amount');
 *   $segments->last()?->get('amount');
 *   $segments->nth(2)?->get('amount');
 *   $segments->filter(fn ($r) => $r->get('amount') > 100)->count();
 *   $segments->each(fn ($r) => process($r));
 *   $segments->toArray(); // retrocompatibility — array of arrays
 */
final readonly class ParseResultCollection
{
    /**
     * @param ParsedRecord[] $records
     */
    public function __construct(private array $records)
    {
    }

    /**
     * First record in the collection, or null when empty.
     */
    public function first(): ?ParsedRecord
    {
        return $this->records[0] ?? null;
    }

    /**
     * Last record in the collection, or null when empty.
     */
    public function last(): ?ParsedRecord
    {
        $count = count($this->records);

        return $count > 0 ? $this->records[$count - 1] : null;
    }

    /**
     * Record at position $index (0-based), or null when out of bounds.
     */
    public function nth(int $index): ?ParsedRecord
    {
        return $this->records[$index] ?? null;
    }

    /**
     * Filters the collection and returns a new one with records satisfying the predicate.
     *
     * @param callable(ParsedRecord): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->records, $predicate)));
    }

    /**
     * Iterates over each record in the collection.
     *
     * @param callable(ParsedRecord): void $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->records as $record) {
            $callback($record);
        }
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return empty($this->records);
    }

    /**
     * Returns records as an array of associative arrays.
     * Maintains retrocompatibility with the previous array-based format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (ParsedRecord $r) => array_merge(['_record' => $r->record()], $r->fields()),
            $this->records,
        );
    }
}
