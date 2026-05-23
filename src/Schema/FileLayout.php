<?php

namespace Husail\EdiSdk\Schema;

use Husail\EdiSdk\Schema\Sequence\GroupNode;
use Husail\EdiSdk\Exceptions\LayoutException;
use Husail\EdiSdk\Schema\Sequence\SequenceNode;
use Husail\EdiSdk\Schema\Sequence\AmbiguousNode;

/**
 * Describes the complete structure of a positional file.
 *
 * Construction via fluent builder:
 *
 *   $layout = FileLayout::define('cnab240')
 *       ->lineLength(240)
 *       ->lineEnding("\r\n")
 *       ->addRecord($fileHeader)
 *       ->addRecord($batchHeader)
 *       ->addRecord($segmentA)
 *       ->addRecord($segmentB)
 *       ->addRecord($batchTrailer)
 *       ->addRecord($fileTrailer)
 *       ->withSequence([
 *           Record::one($fileHeader),
 *           Group::repeat(
 *               identifyBy: fn($line) => match($line[7]) { ... },
 *               children: [...]
 *           ),
 *           Record::one($fileTrailer),
 *       ])
 *       ->build();
 */
final class FileLayout
{
    /** @var array<string, RecordLayout> */
    private array $records = [];

    /** @var SequenceNode[] */
    private array $sequence = [];

    private int    $lineLength = 0;
    private string $lineEnding = "\n";

    private function __construct(
        public readonly string $name,
    ) {
    }

    public static function define(string $name): self
    {
        return new self($name);
    }

    public function lineLength(int $length): self
    {
        $clone             = clone $this;
        $clone->lineLength = $length;

        return $clone;
    }

    public function lineEnding(string $ending): self
    {
        $clone             = clone $this;
        $clone->lineEnding = $ending;

        return $clone;
    }

    /**
     * Registers a RecordLayout in the FileLayout index.
     * Named addRecord() for consistency with RecordLayout::addField().
     */
    public function addRecord(RecordLayout $record): self
    {
        $clone                         = clone $this;
        $clone->records[$record->name] = $record;

        return $clone;
    }

    /**
     * Defines the sequence tree of the file.
     * All RecordLayouts referenced must have been registered via addRecord().
     *
     * @param SequenceNode[] $nodes
     */
    public function withSequence(array $nodes): self
    {
        $clone           = clone $this;
        $clone->sequence = $nodes;

        return $clone;
    }

    /**
     * @throws LayoutException when structural invariants are violated.
     */
    public function build(): self
    {
        $this->assertValid($this->name, $this->lineLength, $this->records, $this->sequence);

        return $this;
    }

    /**
     * @param array<string, RecordLayout> $records
     * @param SequenceNode[] $sequence
     *
     * @throws LayoutException when structural invariants are violated.
     */
    public static function fromArray(
        string $name,
        int    $lineLength,
        string $lineEnding,
        array  $records,
        array  $sequence,
    ): self {
        self::assertValid($name, $lineLength, $records, $sequence);

        $instance             = new self($name);
        $instance->lineLength = $lineLength;
        $instance->lineEnding = $lineEnding;
        $instance->records    = $records;
        $instance->sequence   = $sequence;

        return $instance;
    }

    public function getLineLength(): int
    {
        return $this->lineLength;
    }

    public function getLineEnding(): string
    {
        return $this->lineEnding;
    }

    /** @return SequenceNode[] */
    public function getSequence(): array
    {
        return $this->sequence;
    }

    /** @return array<string, RecordLayout> */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Resolves a RecordLayout by name.
     *
     * @throws LayoutException when the name is not registered.
     */
    public function resolveRecord(string $name): RecordLayout
    {
        return $this->records[$name]
            ?? throw new LayoutException(
                "RecordLayout '{$name}' is not registered in FileLayout '{$this->name}'. "
                . "Call ->addRecord(\$layout) before ->withSequence([...])."
            );
    }

    /**
     * Validates the FileLayout structure.
     * Called by both build() and fromArray() to ensure every construction
     * path passes the same rules.
     *
     * @param array<string, RecordLayout> $records
     * @param SequenceNode[] $sequence
     */
    private static function assertValid(
        string $name,
        int    $lineLength,
        array  $records,
        array  $sequence,
    ): void {
        if ($lineLength <= 0) {
            throw new LayoutException(
                "FileLayout '{$name}': lineLength must be > 0."
            );
        }

        if (empty($sequence)) {
            throw new LayoutException(
                "FileLayout '{$name}': at least one sequence node must be defined."
            );
        }

        self::assertSequenceValid($name, $sequence, $records);
    }

    /**
     * Recursively validates the sequence nodes.
     *
     * Checks:
     *   - all referenced records are registered
     *   - GroupNode has at least one child
     *   - AmbiguousNode has at least one child
     *
     * @param SequenceNode[] $nodes
     * @param array<string, RecordLayout> $records
     */
    private static function assertSequenceValid(string $name, array $nodes, array $records): void
    {
        foreach ($nodes as $node) {
            foreach ($node->recordNames() as $recordName) {
                if (!isset($records[$recordName])) {
                    throw new LayoutException(
                        "FileLayout '{$name}': sequence references RecordLayout '{$recordName}' "
                        . "which is not registered. Call ->addRecord(\$layout) first."
                    );
                }
            }

            if ($node instanceof GroupNode && empty($node->children)) {
                throw new LayoutException(
                    "FileLayout '{$name}': GroupNode must have at least one child."
                );
            }

            if ($node instanceof AmbiguousNode && empty($node->children)) {
                throw new LayoutException(
                    "FileLayout '{$name}': AmbiguousNode must have at least one child."
                );
            }

            if ($node instanceof GroupNode || $node instanceof AmbiguousNode) {
                self::assertSequenceValid($name, $node->children, $records);
            }
        }
    }
}
