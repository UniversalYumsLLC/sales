<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributorCustomer extends Model
{
    protected $fillable = [
        'fulfil_party_id',
        'name',
        'company_urls',
    ];

    protected function casts(): array
    {
        return [
            'company_urls' => 'array',
        ];
    }

    /**
     * Get the distributor (parent customer) this belongs to.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(FulfilCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get the contacts for this distributor customer.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(DistributorCustomerContact::class);
    }

    /**
     * Get the emails associated with this distributor customer.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
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
}
