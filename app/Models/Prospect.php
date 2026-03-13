<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory;

    const STATUS_TARGET = 'target';

    const STATUS_CONTACTED = 'contacted';

    const STATUS_ENGAGED = 'engaged';

    const STATUS_DORMANT = 'dormant';

    /**
     * Status labels and descriptions for the UI.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_TARGET => [
                'label' => 'Target',
                'description' => 'Identified but no contact',
            ],
            self::STATUS_CONTACTED => [
                'label' => 'Contacted',
                'description' => 'Initial outreach made, no response yet',
            ],
            self::STATUS_ENGAGED => [
                'label' => 'Engaged',
                'description' => 'Active dialogue',
            ],
            self::STATUS_DORMANT => [
                'label' => 'Dormant',
                'description' => 'Previous contact, but no recent engagement',
            ],
        ];
    }

    protected $fillable = [
        'company_name',
        'notes',
        'status',
        'created_by',
        'discount_percent',
        'payment_terms',
        'shipping_terms',
        'shelf_life_requirement',
        'vendor_guide',
        'company_urls',
        'broker',
        'broker_commission',
        'broker_company_name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'company_urls' => 'array',
            'broker' => 'boolean',
            'broker_commission' => 'decimal:2',
        ];
    }

    /**
     * Get the email domains associated with this prospect.
     * Extracts domains from company_urls for email matching.
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

        // Normalize the URL for comparison
        $normalizedNew = strtolower(trim($url));

        foreach ($urls as $existing) {
            if (strtolower(trim($existing)) === $normalizedNew) {
                return false; // Already exists
            }
        }

        $urls[] = $url;
        $this->company_urls = $urls;
        $this->save();

        return true;
    }

    /**
     * Check if an email domain matches this prospect's company URLs.
     */
    public function matchesEmailDomain(string $email): bool
    {
        $emailDomain = strtolower(explode('@', $email)[1] ?? '');
        if (empty($emailDomain)) {
            return false;
        }

        return in_array($emailDomain, $this->getEmailDomains());
    }

    /**
     * Get the user who created this prospect.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all contacts for this prospect.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ProspectContact::class);
    }

    /**
     * Get buyer contacts for this prospect.
     */
    public function buyers(): HasMany
    {
        return $this->hasMany(ProspectContact::class)->where('type', 'buyer');
    }

    /**
     * Get accounts payable contacts for this prospect.
     */
    public function accountsPayable(): HasMany
    {
        return $this->hasMany(ProspectContact::class)->where('type', 'accounts_payable');
    }

    /**
     * Get logistics contacts for this prospect.
     */
    public function logistics(): HasMany
    {
        return $this->hasMany(ProspectContact::class)->where('type', 'logistics');
    }

    /**
     * Get uncategorized contacts for this prospect.
     */
    public function uncategorized(): HasMany
    {
        return $this->hasMany(ProspectContact::class)->where('type', 'uncategorized');
    }

    /**
     * Get broker contacts for this prospect.
     */
    public function brokerContacts(): HasMany
    {
        return $this->hasMany(ProspectContact::class)->where('type', 'broker');
    }

    /**
     * Get the products of interest for this prospect.
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProspectProduct::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to exclude dormant prospects (for default listing).
     */
    public function scopeNotDormant($query)
    {
        return $query->where('status', '!=', self::STATUS_DORMANT);
    }
}
