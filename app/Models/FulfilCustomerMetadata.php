<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfilCustomerMetadata extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'fulfil_party_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'fulfil_customer_metadata';

    protected $fillable = [
        'fulfil_party_id',
        'company_urls',
        'broker',
        'broker_commission',
        'broker_company_name',
    ];

    protected function casts(): array
    {
        return [
            'company_urls' => 'array',
            'broker' => 'boolean',
            'broker_commission' => 'decimal:2',
        ];
    }

    /**
     * Get the email domains from company_urls for matching.
     */
    public function getEmailDomains(): array
    {
        if (empty($this->company_urls)) {
            return [];
        }

        $domains = [];
        foreach ($this->company_urls as $url) {
            // Handle both full URLs and plain domains
            $domain = $url;
            if (str_contains($url, '://')) {
                $parsed = parse_url($url);
                $domain = $parsed['host'] ?? $url;
            }
            // Remove www. prefix if present
            $domain = preg_replace('/^www\./i', '', $domain);
            $domains[] = strtolower($domain);
        }

        return array_unique($domains);
    }

    /**
     * Add a company URL if it doesn't already exist.
     */
    public function addCompanyUrl(string $url): bool
    {
        $urls = $this->company_urls ?? [];

        $normalizedNew = strtolower(trim($url));

        foreach ($urls as $existing) {
            if (strtolower(trim($existing)) === $normalizedNew) {
                return false;
            }
        }

        $urls[] = $url;
        $this->company_urls = $urls;
        $this->save();

        return true;
    }

    /**
     * Get or create metadata for a Fulfil customer.
     */
    public static function findOrCreateForCustomer(int $fulfilPartyId): self
    {
        return self::firstOrCreate(
            ['fulfil_party_id' => $fulfilPartyId],
            ['company_urls' => []]
        );
    }

    /**
     * Get contact metadata records for this customer.
     */
    public function contactMetadata()
    {
        return $this->hasMany(FulfilContactMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get uncategorized contacts for this customer.
     */
    public function uncategorizedContacts()
    {
        return $this->hasMany(FulfilUncategorizedContact::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get broker contacts for this customer.
     */
    public function brokerContacts()
    {
        return $this->hasMany(FulfilBrokerContact::class, 'fulfil_party_id', 'fulfil_party_id');
    }
}
