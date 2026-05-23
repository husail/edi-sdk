<?php

namespace Husail\EdiSdk\Results;

/**
 * Result object for validation operations.
 */
final readonly class ValidationResult
{
    /** @param ValidationError[] $errors */
    public function __construct(
        private array $errors = [],
    ) {
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return ValidationError[] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return ValidationError[] */
    public function errorsForLine(int $line): array
    {
        return array_values(
            array_filter($this->errors, fn (ValidationError $e) => $e->line === $line)
        );
    }

    /** @return ValidationError[] */
    public function errorsForRecord(string $record): array
    {
        return array_values(
            array_filter($this->errors, fn (ValidationError $e) => $e->record === $record)
        );
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }
}
