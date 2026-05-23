<?php

namespace Husail\EdiSdk\Results;

/**
 * Result object for parse operations.
 */
final readonly class ParseResult
{
    /** @param ParsedRecord[] $records */
    public function __construct(
        private array $records,
    ) {
    }

    public function first(string $record): ?ParsedRecord
    {
        foreach ($this->records as $r) {
            if ($r->record() === $record) {
                return $r;
            }
        }

        return null;
    }

    public function records(string $record): ParseResultCollection
    {
        return new ParseResultCollection(
            array_values(
                array_filter($this->records, fn (ParsedRecord $r) => $r->record() === $record)
            )
        );
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
     * Kept for backwards compatibility with earlier APIs.
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
