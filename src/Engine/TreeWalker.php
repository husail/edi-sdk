<?php

namespace Husail\EdiSdk\Engine;

use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\Sequence\ManyNode;
use Husail\EdiSdk\Exceptions\ParseException;
use Husail\EdiSdk\Schema\Sequence\GroupNode;
use Husail\EdiSdk\Schema\Sequence\RecordNode;
use Husail\EdiSdk\Engine\Visitors\LineVisitor;
use Husail\EdiSdk\Schema\Sequence\SequenceNode;
use Husail\EdiSdk\Schema\Sequence\AmbiguousNode;

/**
 * Navigates a FileLayout sequence tree and delegates each line to a visitor.
 */
final class TreeWalker
{
    /** @var string[] */
    private array $lines = [];

    private int $cursor = 0;

    public function __construct(private readonly FileLayout $layout)
    {
    }

    /**
     * Walks the configured sequence and delegates each consumed line to a visitor.
     *
     * @throws ParseException when the input does not satisfy sequence constraints.
     */
    public function walk(string $content, LineVisitor $visitor): void
    {
        $this->lines  = $this->splitLines($content);
        $this->cursor = 0;

        $this->processSequence($this->layout->getSequence(), $visitor);
    }

    /**
     * @param SequenceNode[] $nodes
     */
    private function processSequence(array $nodes, LineVisitor $visitor): void
    {
        foreach ($nodes as $node) {
            if (!($node instanceof ManyNode)) {
                $this->processNode($node, $visitor);
                continue;
            }

            // ManyNode at the root sequence level needs a stop condition built from
            // the record's const fields — delegated to buildStopWhen().
            $this->processManyNode($node, $visitor, $this->buildStopWhen($node));
        }
    }

    private function processNode(SequenceNode $node, LineVisitor $visitor): void
    {
        match (true) {
            $node instanceof RecordNode    => $this->processRecordNode($node, $visitor),
            $node instanceof ManyNode      => $this->processManyNode($node, $visitor),
            $node instanceof GroupNode     => $this->processGroupNode($node, $visitor),
            $node instanceof AmbiguousNode => $this->processAmbiguousNode($node, $visitor),
            default => throw new ParseException('Unknown sequence node type: ' . get_class($node)),
        };
    }

    private function processRecordNode(RecordNode $node, LineVisitor $visitor): void
    {
        if (!$this->hasLines()) {
            if ($node->required) {
                throw new ParseException(
                    "Expected record '{$node->layout->name}' but reached end of file."
                );
            }
            return;
        }

        $visitor->visit($this->currentLine(), $node->layout, $this->lineNumber());
        $this->advance();
    }

    /**
     * @param \Closure(string): ?string|null $stopWhen
     */
    private function processManyNode(
        ManyNode    $node,
        LineVisitor $visitor,
        ?\Closure   $stopWhen = null,
    ): void {
        while ($this->hasLines()) {
            if ($stopWhen !== null && $stopWhen($this->currentLine()) === null) {
                break;
            }

            $visitor->visit($this->currentLine(), $node->layout, $this->lineNumber());
            $this->advance();
        }
    }

    private function processGroupNode(GroupNode $node, LineVisitor $visitor): void
    {
        while ($this->hasLines()) {
            $name = ($node->identifyBy)($this->currentLine());

            if ($name === null) {
                break;
            }

            $cursorBefore = $this->cursor;

            foreach ($node->children as $child) {
                if (!$this->hasLines()) {
                    break;
                }

                if ($child instanceof ManyNode) {
                    $this->processManyNode($child, $visitor, $node->identifyBy->toClosure());
                } else {
                    $this->processNode($child, $visitor);
                }
            }

            if ($this->cursor === $cursorBefore) {
                throw new ParseException(
                    "Walker stalled at line {$this->lineNumber()}: group '{$name}' was identified "
                    . "but no child consumed the line. Check the sequence definition."
                );
            }
        }
    }

    private function processAmbiguousNode(AmbiguousNode $node, LineVisitor $visitor): void
    {
        $knownNames = array_merge(
            ...array_map(fn (SequenceNode $n) => $n->recordNames(), $node->children)
        );

        while ($this->hasLines()) {
            $name = ($node->identifyBy)($this->currentLine());

            if ($name === null || !in_array($name, $knownNames, true)) {
                break;
            }

            $layout = $this->layout->resolveRecord($name);
            $visitor->visit($this->currentLine(), $layout, $this->lineNumber());
            $this->advance();
        }
    }

    /**
     * Builds a stop condition for a ManyNode.
     *
     * Const fields are treated as a fingerprint. The line belongs to the node
     * only when every const field matches. If no const field exists, any line
     * with the expected layout length is accepted.
     */
    private function buildStopWhen(ManyNode $node): \Closure
    {
        $layout      = $node->layout;
        $constFields = array_filter($layout->getFields(), fn ($f) => $f->const !== null);

        if (empty($constFields)) {
            return fn (string $line): ?string => mb_strlen($line) === $layout->getLineLength()
                ? 'match'
                : null;
        }

        return function (string $line) use ($constFields, $layout): ?string {
            foreach ($constFields as $field) {
                $raw = mb_substr($line, $field->offset(), $field->length);
                if (trim($raw) !== trim((string) $field->const)) {
                    return null;
                }
            }
            return $layout->name;
        };
    }

    /**
     * Splits content by CRLF, CR, or LF and discards empty lines.
     * Line numbers reported by the walker refer to this filtered sequence.
     *
     * @return string[]
     */
    private function splitLines(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        return array_values(array_filter($lines, fn (string $l) => $l !== ''));
    }

    private function currentLine(): string
    {
        return $this->lines[$this->cursor];
    }

    private function lineNumber(): int
    {
        return $this->cursor + 1;
    }

    private function hasLines(): bool
    {
        return $this->cursor < count($this->lines);
    }

    private function advance(): void
    {
        $this->cursor++;
    }
}
