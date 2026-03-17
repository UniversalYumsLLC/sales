<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when invoice PDF generation fails.
 *
 * Used for cases like:
 * - Customer requires SKU mappings but some SKUs are not mapped
 * - Invoice data is incomplete or invalid
 */
class InvoicePdfException extends Exception
{
    /**
     * List of unmapped SKUs (if applicable).
     */
    protected array $unmappedSkus = [];

    public function __construct(string $message, array $unmappedSkus = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->unmappedSkus = $unmappedSkus;
    }

    /**
     * Get the list of unmapped SKUs.
     */
    public function getUnmappedSkus(): array
    {
        return $this->unmappedSkus;
    }

    /**
     * Check if this exception is due to unmapped SKUs.
     */
    public function hasUnmappedSkus(): bool
    {
        return ! empty($this->unmappedSkus);
    }
}
