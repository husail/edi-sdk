<?php

namespace Husail\EdiSdk\Schema\Mapping;

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Schema\Sequence\ManyNode;
use Husail\EdiSdk\Schema\Sequence\GroupNode;
use Husail\EdiSdk\Exceptions\LayoutException;
use Husail\EdiSdk\Schema\Sequence\IdentifyBy;
use Husail\EdiSdk\Schema\Sequence\RecordNode;
use Husail\EdiSdk\Schema\Sequence\SequenceNode;
use Husail\EdiSdk\Schema\Sequence\AmbiguousNode;

/**
 * Builds a FileLayout from normalized array input.
 *
 * This is the only SDK component that knows the array schema used for layout
 * definitions. Drivers convert their source format to arrays and delegate here.
 *
 * Expected array schema:
 *   [
 *     'name'        => 'cnab240',
 *     'line_length' => 240,
 *     'line_ending' => "\r\n",
 *     'records'     => [
 *       [
 *         'name'   => 'file_header',
 *         'fields' => [
 *           ['name' => 'bank_code', 'pos' => 1, 'len' => 3, 'type' => 'numeric', 'const' => '341'],
 *         ],
 *       ],
 *     ],
 *     'sequence' => [
 *       ['type' => 'record', 'record' => 'file_header', 'required' => true],
 *       [
 *         'type'                 => 'group',
 *         'identify_by_position' => 8,
 *         'children'             => [...],
 *       ],
 *     ],
 *   ]
 */
final class ArrayLayoutMapper
{
    /** @param array<string, mixed> $data */
    public function map(array $data): FileLayout
    {
        $records  = $this->buildRecords($data);
        $sequence = $this->buildSequence($data['sequence'] ?? [], $records);

        return FileLayout::fromArray(
            name:       $data['name']       ?? throw new LayoutException("Layout missing 'name'."),
            lineLength: $data['line_length'] ?? throw new LayoutException("Layout missing 'line_length'."),
            lineEnding: $this->parseLineEnding($data['line_ending'] ?? '\n'),
            records:    $records,
            sequence:   $sequence,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, RecordLayout>
     */
    private function buildRecords(array $data): array
    {
        $records    = [];
        $lineLength = $data['line_length'] ?? 0;

        foreach ($data['records'] ?? [] as $recordData) {
            $name   = $recordData['name'] ?? throw new LayoutException("Record missing 'name'.");
            $fields = [];

            foreach ($recordData['fields'] ?? [] as $fieldData) {
                $fields[] = $this->buildField($fieldData);
            }

            $records[$name] = RecordLayout::fromArray(
                name:       $name,
                lineLength: $recordData['line_length'] ?? $lineLength,
                fields:     $fields,
            );
        }

        return $records;
    }

    /** @param array<string, mixed> $data */
    private function buildField(array $data): FieldDefinition
    {
        return new FieldDefinition(
            name:          $data['name']           ?? throw new LayoutException("Field missing 'name'."),
            position:      $data['pos']            ?? throw new LayoutException("Field missing 'pos'."),
            length:        $data['len']            ?? throw new LayoutException("Field missing 'len'."),
            type:          FieldType::from($data['type'] ?? throw new LayoutException("Field missing 'type'.")),
            const:         $data['const']          ?? null,
            default:       $data['default']        ?? null,
            format:        $data['format']         ?? null,
            cast:          $data['cast']           ?? null,
            decimalPlaces: $data['decimal_places'] ?? 0,
            required:      $data['required']       ?? true,
            paddingChar:   $data['padding_char']   ?? null,
            paddingSide:   isset($data['padding_side'])
                               ? PaddingSide::from($data['padding_side'])
                               : null,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, RecordLayout> $records
     * @return SequenceNode[]
     */
    private function buildSequence(array $nodes, array $records): array
    {
        return array_map(fn (array $node) => $this->buildNode($node, $records), $nodes);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     */
    private function buildNode(array $node, array $records): SequenceNode
    {
        $type = $node['type'] ?? throw new LayoutException("Sequence node missing 'type'.");

        return match ($type) {
            'record'    => $this->buildRecordNode($node, $records, required: true),
            'optional'  => $this->buildRecordNode($node, $records, required: false),
            'many'      => $this->buildManyNode($node, $records),
            'group'     => $this->buildGroupNode($node, $records),
            'ambiguous' => $this->buildAmbiguousNode($node, $records),
            default     => throw new LayoutException("Unknown sequence node type: '{$type}'."),
        };
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     */
    private function buildRecordNode(array $node, array $records, bool $required): RecordNode
    {
        $name   = $node['record'] ?? throw new LayoutException("Sequence node missing 'record'.");
        $layout = $records[$name] ?? throw new LayoutException("Record '{$name}' not found in records list.");

        return new RecordNode(layout: $layout, required: $required);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     */
    private function buildManyNode(array $node, array $records): ManyNode
    {
        $name   = $node['record'] ?? throw new LayoutException("Sequence node missing 'record'.");
        $layout = $records[$name] ?? throw new LayoutException("Record '{$name}' not found in records list.");

        return new ManyNode(layout: $layout);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     */
    private function buildGroupNode(array $node, array $records): GroupNode
    {
        $identifyBy = IdentifyBy::fromCallable($this->buildIdentifyBy($node));
        $children   = $this->buildSequence($node['children'] ?? [], $records);

        return new GroupNode(children: $children, identifyBy: $identifyBy);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     */
    private function buildAmbiguousNode(array $node, array $records): AmbiguousNode
    {
        $identifyBy = IdentifyBy::fromCallable($this->buildIdentifyBy($node));

        // `children` is optional when composite `identify_by` is present.
        // When omitted, children are inferred from identify_by rules to reduce
        // redundancy and avoid configuration drift.
        $children = isset($node['children'])
            ? $this->buildSequence($node['children'], $records)
            : $this->buildChildrenFromIdentifyBy($node, $records);

        return new AmbiguousNode(children: $children, identifyBy: $identifyBy);
    }

    /**
     * Infers AmbiguousNode children from composite identify_by rules.
     *
     * Each unique record in the rules becomes a ManyNode child, preserving
     * declaration order so that recordNames() covers all valid identifiers.
     *
     * @param array<string, mixed> $node
     * @param array<string, RecordLayout> $records
     * @return SequenceNode[]
     */
    private function buildChildrenFromIdentifyBy(array $node, array $records): array
    {
        if (!isset($node['identify_by']) || !is_array($node['identify_by'])) {
            throw new LayoutException(
                "Ambiguous node requires either 'children' or a composite 'identify_by'."
            );
        }

        $children = [];
        $added    = [];

        foreach ($node['identify_by'] as $rule) {
            $record = $rule['record']
                ?? throw new LayoutException("Composite identify_by rule missing 'record'.");

            if (isset($added[$record])) {
                continue;
            }

            $layout = $records[$record]
                ?? throw new LayoutException(
                    "Record '{$record}' referenced in identify_by not found in records list."
                );

            $children[]     = new ManyNode(layout: $layout);
            $added[$record] = true;
        }

        return $children;
    }

    /**
     * Builds the identifyBy callable from the node definition.
     *
     * Supports two formats:
     *
     * Simple (single position):
     *   identify_by_position: 14
     *   children with identifier_value
     *
     * Composite (multiple conditions — required for J vs J52, B vs B PIX):
     *   identify_by:
     *     - record: segment_j52
     *       match:
     *         - { pos: 14, len: 1, value: "J" }
     *         - { pos: 18, len: 2, value: "52" }
     *     - record: segment_j
     *       match:
     *         - { pos: 14, len: 1, value: "J" }
     *
     * In the composite format, order matters — more specific rules must come first.
     * Supports "value" (equality) and "in" (list of accepted values).
     *
     * @param array<string, mixed> $node
     */
    private function buildIdentifyBy(array $node): callable
    {
        if (isset($node['identify_by'])) {
            return $this->buildCompositeIdentifyBy($node['identify_by']);
        }

        $position = $node['identify_by_position']
            ?? throw new LayoutException(
                "Group/Ambiguous node missing 'identify_by_position' or 'identify_by'."
            );

        $map = [];
        foreach ($node['children'] ?? [] as $child) {
            if (!isset($child['identifier_value'])) {
                continue;
            }
            $map[$child['identifier_value']] = isset($child['record'])
                ? $child['record']
                : $child['identifier_value'];
        }

        $offset = $position - 1;

        return function (string $line) use ($offset, $map): ?string {
            return $map[$line[$offset] ?? ''] ?? null;
        };
    }

    /**
     * Builds a composite identification callable.
     *
     * Tests each rule in order and returns the first matching record name.
     * Within each rule, all match conditions must be satisfied.
     *
     * @param array<int, array{record: string, match: array<int, array{pos: int, len: int, value?: string, in?: string[]}>}> $rules
     */
    private function buildCompositeIdentifyBy(array $rules): callable
    {
        return function (string $line) use ($rules): ?string {
            foreach ($rules as $rule) {
                $matched = true;

                foreach ($rule['match'] as $match) {
                    $raw = mb_substr($line, $match['pos'] - 1, $match['len']);

                    if (array_key_exists('value', $match) && $raw !== $match['value']) {
                        $matched = false;
                        break;
                    }

                    if (array_key_exists('in', $match) && !in_array($raw, $match['in'], true)) {
                        $matched = false;
                        break;
                    }
                }

                if ($matched) {
                    return $rule['record'];
                }
            }

            return null;
        };
    }

    private function parseLineEnding(string $value): string
    {
        return str_replace(['\r\n', '\r', '\n'], ["\r\n", "\r", "\n"], $value);
    }
}
