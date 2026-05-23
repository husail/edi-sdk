<?php

namespace Husail\EdiSdk\Engine\Visitors;

use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\PaddingSide;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\FieldDefinition;
use Husail\EdiSdk\Results\ValidationError;
use Husail\EdiSdk\Results\ValidationResult;

/**
 * Visitor that validates each line and accumulates errors.
 *
 * Validation order per line:
 *   1. Line length
 *   2. Const fields — exact value match
 *   3. Required ALPHA fields
 *   4. Custom RecordLayout validators
 */
final class ValidatorVisitor implements LineVisitor
{
    /** @var ValidationError[] */
    private array $errors = [];

    public function visit(string $line, RecordLayout $layout, int $lineNumber): void
    {
        if (mb_strlen($line) !== $layout->getLineLength()) {
            $this->addError($lineNumber, $layout->name, '_record', sprintf(
                "Expected line length %d, got %d.",
                $layout->getLineLength(),
                mb_strlen($line)
            ));
        }

        $data = ['_record' => $layout->name];

        foreach ($layout->getFields() as $field) {
            $raw                = mb_substr($line, $field->offset(), $field->length);
            $data[$field->name] = $raw;

            $this->validateField($raw, $field, $lineNumber, $layout->name);
        }

        foreach ($layout->getValidators() as $validator) {
            $error = $validator($data);
            if ($error !== null) {
                $this->addError($lineNumber, $layout->name, '_record', $error);
            }
        }
    }

    public function getResult(): ValidationResult
    {
        return new ValidationResult($this->errors);
    }

    /**
     * @param string $raw Raw extracted bytes from the line.
     *
     * required is only enforced for ALPHA — NUMERIC zeros are indistinguishable
     * from unfilled fields in a fixed-width format.
     */
    private function validateField(
        string          $raw,
        FieldDefinition $field,
        int             $lineNumber,
        string          $recordName,
    ): void {
        if ($field->const !== null) {
            $expected = str_pad(
                $field->const,
                $field->length,
                $field->resolvedPaddingChar(),
                $field->resolvedPaddingSide() === PaddingSide::LEFT ? STR_PAD_LEFT : STR_PAD_RIGHT,
            );

            if ($raw !== $expected) {
                $this->addError(
                    $lineNumber,
                    $recordName,
                    $field->name,
                    "Expected const '{$field->const}', got '{$raw}'."
                );
            }
        }

        if ($field->required && $field->const === null && $field->type === FieldType::ALPHA) {
            $stripped = trim($raw, $field->resolvedPaddingChar());
            if ($stripped === '') {
                $this->addError(
                    $lineNumber,
                    $recordName,
                    $field->name,
                    "Field '{$field->name}' is required but is empty."
                );
            }
        }
    }

    private function addError(int $line, string $record, string $field, string $message): void
    {
        $this->errors[] = new ValidationError(
            line:    $line,
            record:  $record,
            field:   $field,
            message: $message,
        );
    }
}
