<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when an admin payroll breakdown spreadsheet cannot be understood.
 *
 * Carries enough context for the UI to tell the admin exactly which columns
 * were expected and which ones the file actually contained.
 */
class BreakdownFileParseException extends Exception
{
    /**
     * @param  array<int, string>  $missingColumns
     * @param  array<int, string>  $foundColumns
     */
    public function __construct(
        string $message,
        public readonly array $missingColumns = [],
        public readonly array $foundColumns = [],
    ) {
        parent::__construct($message);
    }

    public static function headerRowNotFound(): self
    {
        return new self('Could not find header row with required columns in the first 10 rows.');
    }

    /**
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $found
     */
    public static function missingColumns(array $missing, array $found): self
    {
        return new self(
            'Missing required columns: '.implode(', ', $missing),
            $missing,
            $found,
        );
    }
}
