<?php

namespace Husail\EdiSdk\Results;

/**
 * Represents a validation error on a specific field or record.
 */
final readonly class ValidationError
{
    public function __construct(
        /** Line number in the file (1-based). */
        public int    $line,

        /** Name of the RecordLayout the line belongs to. */
        public string $record,

        /** Name of the field where the error occurred, or '_record' for record/sequence errors. */
        public string $field,

        /** Descriptive error message. */
        public string $message,
    ) {
    }
}
