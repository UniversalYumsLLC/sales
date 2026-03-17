<?php

namespace App\Http\Controllers;

use App\Models\CustomerSku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerSkuController extends Controller
{
    /**
     * Get all SKU mappings for a customer.
     */
    public function index(int $customerId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->canManageCustomers()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $skuMappings = CustomerSku::getForCustomer($customerId)
            ->map(fn ($sku) => [
                'id' => $sku->id,
                'yums_sku' => $sku->yums_sku,
                'customer_sku' => $sku->customer_sku,
            ]);

        return response()->json(['skus' => $skuMappings]);
    }

    /**
     * Create a new SKU mapping for a customer.
     */
    public function store(Request $request, int $customerId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->canManageCustomers()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'yums_sku' => 'required|string|max:100',
            'customer_sku' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for duplicate Yums SKU for this customer
        $existing = CustomerSku::where('fulfil_party_id', $customerId)
            ->where('yums_sku', $request->input('yums_sku'))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This Yums SKU is already mapped for this customer',
            ], 422);
        }

        $customerSku = CustomerSku::create([
            'fulfil_party_id' => $customerId,
            'yums_sku' => $request->input('yums_sku'),
            'customer_sku' => $request->input('customer_sku'),
        ]);

        return response()->json([
            'message' => 'SKU mapping created successfully',
            'sku' => [
                'id' => $customerSku->id,
                'yums_sku' => $customerSku->yums_sku,
                'customer_sku' => $customerSku->customer_sku,
            ],
        ], 201);
    }

    /**
     * Update an existing SKU mapping.
     */
    public function update(Request $request, int $customerId, int $skuId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->canManageCustomers()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $customerSku = CustomerSku::where('id', $skuId)
            ->where('fulfil_party_id', $customerId)
            ->first();

        if (! $customerSku) {
            return response()->json(['message' => 'SKU mapping not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_sku' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customerSku->update([
            'customer_sku' => $request->input('customer_sku'),
        ]);

        return response()->json([
            'message' => 'SKU mapping updated successfully',
            'sku' => [
                'id' => $customerSku->id,
                'yums_sku' => $customerSku->yums_sku,
                'customer_sku' => $customerSku->customer_sku,
            ],
        ]);
    }

    /**
     * Delete a SKU mapping.
     */
    public function destroy(int $customerId, int $skuId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->canManageCustomers()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $customerSku = CustomerSku::where('id', $skuId)
            ->where('fulfil_party_id', $customerId)
            ->first();

        if (! $customerSku) {
            return response()->json(['message' => 'SKU mapping not found'], 404);
        }

        $customerSku->delete();

        return response()->json(['message' => 'SKU mapping deleted successfully']);
    }
}
