<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CustomerSku extends Model
{
    protected $fillable = [
        'fulfil_party_id',
        'yums_sku',
        'customer_sku',
    ];

    /**
     * Get the customer metadata for this SKU mapping.
     */
    public function customerMetadata(): BelongsTo
    {
        return $this->belongsTo(LocalCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get all SKU mappings for a customer.
     */
    public static function getForCustomer(int $fulfilPartyId): Collection
    {
        return self::where('fulfil_party_id', $fulfilPartyId)->get();
    }

    /**
     * Get the customer SKU for a Yums SKU.
     */
    public static function getCustomerSku(int $fulfilPartyId, string $yumsSku): ?string
    {
        $mapping = self::where('fulfil_party_id', $fulfilPartyId)
            ->where('yums_sku', $yumsSku)
            ->first();

        return $mapping?->customer_sku;
    }

    /**
     * Check if all SKUs in a list are mapped for a customer.
     *
     * @return Collection Collection of unmapped Yums SKUs
     */
    public static function getUnmappedSkus(int $fulfilPartyId, array $yumsSkus): Collection
    {
        $mappedSkus = self::where('fulfil_party_id', $fulfilPartyId)
            ->whereIn('yums_sku', $yumsSkus)
            ->pluck('yums_sku')
            ->toArray();

        return collect($yumsSkus)->diff($mappedSkus)->values();
    }

    /**
     * Get a map of Yums SKU => Customer SKU for a customer.
     */
    public static function getSkuMap(int $fulfilPartyId): array
    {
        return self::where('fulfil_party_id', $fulfilPartyId)
            ->pluck('customer_sku', 'yums_sku')
            ->toArray();
    }
}
