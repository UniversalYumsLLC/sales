<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGmailToken extends Model
{
    protected $fillable = [
        'user_id',
        'gmail_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'history_id',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }

    /**
     * Check if the token will expire soon (within 5 minutes).
     */
    public function willExpireSoon(): bool
    {
        return $this->token_expires_at->subMinutes(5)->isPast();
    }
}
