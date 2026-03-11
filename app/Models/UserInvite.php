<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvite extends Model
{
    protected $fillable = [
        'email',
        'role',
        'invited_by',
    ];

    /**
     * Get the user who sent the invite
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if an email has a pending invite
     */
    public static function hasInvite(string $email): bool
    {
        return static::where('email', strtolower($email))->exists();
    }

    /**
     * Get invite for an email
     */
    public static function getByEmail(string $email): ?self
    {
        return static::where('email', strtolower($email))->first();
    }
}
