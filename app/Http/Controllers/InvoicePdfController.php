<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoicePdfException;
use App\Models\EmailTemplate;
use App\Services\ArAutomationService;
use App\Services\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoicePdfController extends Controller
{
    protected InvoicePdfService $pdfService;

    protected ArAutomationService $automationService;

    public function __construct(InvoicePdfService $pdfService, ArAutomationService $automationService)
    {
        $this->pdfService = $pdfService;
        $this->automationService = $automationService;
    }

    /**
     * Download an invoice PDF.
     *
     * Returns a cached PDF if one exists, otherwise generates a new one.
     *
     * @param  int  $id  Fulfil invoice ID
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $storagePath = $this->pdfService->getOrGenerate($id);
            $fullPath = $this->pdfService->getFullPath($storagePath);

            // Extract invoice number from filename for Content-Disposition
            $filename = basename($storagePath);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (InvoicePdfException $e) {
            Log::warning('Invoice PDF download failed: SKU mapping error', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'unmapped_skus' => $e->getUnmappedSkus(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'sku_mapping_required',
                'unmapped_skus' => $e->getUnmappedSkus(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Invoice PDF download failed', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate invoice PDF: '.$e->getMessage(),
                'error' => 'generation_failed',
            ], 500);
        }
    }

    /**
     * Regenerate an invoice PDF.
     *
     * Always fetches fresh data from Fulfil and creates a new PDF.
     *
     * @param  int  $id  Fulfil invoice ID
     */
    public function regenerate(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $storagePath = $this->pdfService->regenerate($id);
            $fullPath = $this->pdfService->getFullPath($storagePath);

            // Extract invoice number from filename for Content-Disposition
            $filename = basename($storagePath);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (InvoicePdfException $e) {
            Log::warning('Invoice PDF regeneration failed: SKU mapping error', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'unmapped_skus' => $e->getUnmappedSkus(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'sku_mapping_required',
                'unmapped_skus' => $e->getUnmappedSkus(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Invoice PDF regeneration failed', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to regenerate invoice PDF: '.$e->getMessage(),
                'error' => 'generation_failed',
            ], 500);
        }
    }

    /**
     * Resend an AR automation email for an invoice.
     *
     * This endpoint allows manual triggering of AR emails for testing.
     *
     * @param  int  $id  Fulfil invoice ID
     */
    public function resendEmail(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email_type' => ['required', 'string', 'in:'.implode(',', [
                EmailTemplate::TYPE_INITIAL_INVOICE,
                EmailTemplate::TYPE_INITIAL_INVOICE_AP_PORTAL,
                EmailTemplate::TYPE_INVOICE_MODIFIED,
                EmailTemplate::TYPE_INVOICE_MODIFIED_AP_PORTAL,
                EmailTemplate::TYPE_DUE_REMINDER,
                EmailTemplate::TYPE_OVERDUE_NOTIFICATION,
                EmailTemplate::TYPE_OVERDUE_FOLLOWUP,
            ])],
        ]);

        try {
            // Pass the Fulfil ID directly - the service will sync as needed
            $sent = $this->automationService->resendEmail(
                $id,
                $validated['email_type']
            );

            if ($sent) {
                return response()->json([
                    'message' => 'Email sent successfully',
                    'email_type' => $validated['email_type'],
                ]);
            }

            return response()->json([
                'message' => 'Failed to send email',
                'error' => 'send_failed',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Invoice email resend failed', [
                'invoice_id' => $id,
                'email_type' => $validated['email_type'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to send email: '.$e->getMessage(),
                'error' => 'send_failed',
            ], 500);
        }
    }
}
