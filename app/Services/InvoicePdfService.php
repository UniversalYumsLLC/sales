<?php

namespace App\Services;

use App\DTOs\Invoice\InvoicePdfDto;
use App\Exceptions\InvoicePdfException;
use App\Models\CustomerSku;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Generate PDF for an invoice.
     *
     * @param  int  $invoiceId  Fulfil invoice ID
     * @param  bool  $forceRegenerate  Skip cache and regenerate
     * @return string Path to generated PDF
     *
     * @throws InvoicePdfException if generation fails (e.g., unmapped SKUs)
     */
    public function generate(int $invoiceId, bool $forceRegenerate = false): string
    {
        // Fetch detailed invoice data from Fulfil
        $invoiceData = $this->fulfil->getInvoiceForPdf($invoiceId);

        // Build the DTO
        $dto = InvoicePdfDto::fromFulfil($invoiceData);

        // Check if we have a cached PDF (unless forcing regeneration)
        $pdfPath = $this->getPdfPath($dto->number);
        if (! $forceRegenerate && Storage::disk('local')->exists($pdfPath)) {
            Log::info('Returning cached invoice PDF', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $dto->number,
                'path' => $pdfPath,
            ]);

            return $pdfPath;
        }

        // Check customer AR settings (SKU requirements, discount)
        $arSettings = $this->fulfil->getCustomerArSettings($dto->customerId);
        $hasCustomerSkus = false;

        if ($arSettings['requires_customer_skus']) {
            // Validate that all SKUs are mapped
            $productCodes = $dto->getProductCodes();
            $unmappedSkus = CustomerSku::getUnmappedSkus($dto->customerId, $productCodes);

            if ($unmappedSkus->isNotEmpty()) {
                Log::warning('Invoice PDF generation blocked: unmapped SKUs', [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $dto->number,
                    'customer_id' => $dto->customerId,
                    'unmapped_skus' => $unmappedSkus->toArray(),
                ]);

                throw new InvoicePdfException(
                    'Cannot generate invoice PDF: customer requires SKU mappings but the following SKUs are not mapped: '.
                    $unmappedSkus->implode(', '),
                    $unmappedSkus->toArray()
                );
            }

            // Apply SKU mappings
            $skuMap = CustomerSku::getSkuMap($dto->customerId);
            $dto = $dto->withCustomerSkus($skuMap);
            $hasCustomerSkus = true;

            Log::info('Applied customer SKU mappings to invoice PDF', [
                'invoice_id' => $invoiceId,
                'customer_id' => $dto->customerId,
                'mappings_applied' => count($skuMap),
            ]);
        }

        // Render the PDF
        $pdf = $this->renderPdf($dto, $hasCustomerSkus, $arSettings['invoice_discount'] ?? null);

        // Store to disk
        $storedPath = $this->storePdf($pdf, $dto->number);

        Log::info('Generated invoice PDF', [
            'invoice_id' => $invoiceId,
            'invoice_number' => $dto->number,
            'path' => $storedPath,
            'regenerated' => $forceRegenerate,
        ]);

        return $storedPath;
    }

    /**
     * Get or generate PDF (returns cached if exists).
     *
     * @param  int  $invoiceId  Fulfil invoice ID
     * @return string Path to PDF
     */
    public function getOrGenerate(int $invoiceId): string
    {
        return $this->generate($invoiceId, forceRegenerate: false);
    }

    /**
     * Force regenerate PDF (always fetches fresh data).
     *
     * @param  int  $invoiceId  Fulfil invoice ID
     * @return string Path to PDF
     */
    public function regenerate(int $invoiceId): string
    {
        return $this->generate($invoiceId, forceRegenerate: true);
    }

    /**
     * Check if a cached PDF exists for an invoice.
     *
     * @param  string  $invoiceNumber  Invoice number (e.g., "INV-12345")
     */
    public function hasCachedPdf(string $invoiceNumber): bool
    {
        return Storage::disk('local')->exists($this->getPdfPath($invoiceNumber));
    }

    /**
     * Get the storage path for an invoice PDF.
     */
    protected function getPdfPath(string $invoiceNumber): string
    {
        // Sanitize the invoice number for safe filesystem usage
        $safeNumber = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $invoiceNumber);

        return "invoices/{$safeNumber}.pdf";
    }

    /**
     * Render PDF from DTO using Blade template.
     */
    protected function renderPdf(InvoicePdfDto $dto, bool $hasCustomerSkus = false, ?float $invoiceDiscount = null): \Barryvdh\DomPDF\PDF
    {
        // Prepare data for the Blade template (matching expected variable names from spec)
        $data = [
            'invoice' => $dto,
            'invoice_address' => $dto->billToAddress,
            'ship_to_address' => $dto->shipToAddress,
            'ship_to_code' => $dto->shipToCode,
            'ship_to_name' => $dto->shipToName,
            'payment_term' => (object) ['name' => $dto->paymentTermName],
            'line_items' => $dto->lineItems,
            'discount_lines' => $dto->discountLines,
            'has_customer_skus' => $hasCustomerSkus,
            'invoice_discount' => $invoiceDiscount,
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('letter', 'portrait');
    }

    /**
     * Store PDF to disk.
     *
     * @return string The storage path
     */
    protected function storePdf(\Barryvdh\DomPDF\PDF $pdf, string $invoiceNumber): string
    {
        $path = $this->getPdfPath($invoiceNumber);

        // Ensure the invoices directory exists
        $directory = dirname($path);
        if (! Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }

        // Store the PDF content
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Get the full filesystem path to a stored PDF.
     */
    public function getFullPath(string $storagePath): string
    {
        return Storage::disk('local')->path($storagePath);
    }

    /**
     * Delete a cached PDF.
     */
    public function deleteCachedPdf(string $invoiceNumber): bool
    {
        $path = $this->getPdfPath($invoiceNumber);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->delete($path);
        }

        return false;
    }
}
