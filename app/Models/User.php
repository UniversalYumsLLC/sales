<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';

    const ROLE_USER = 'user';

    const ROLE_SALESPERSON = 'salesperson';

    /**
     * Get all available roles.
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_USER => 'Accounts Receivable',
            self::ROLE_SALESPERSON => 'Salesperson',
            self::ROLE_ADMIN => 'Admin',
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is a salesperson
     */
    public function isSalesperson(): bool
    {
        return $this->role === self::ROLE_SALESPERSON;
    }

    /**
     * Check if user can access Gmail integration (salesperson or admin)
     */
    public function canAccessGmailIntegration(): bool
    {
        return $this->isSalesperson() || $this->isAdmin();
    }

    /**
     * Check if user can manage customers (salesperson or admin)
     */
    public function canManageCustomers(): bool
    {
        return $this->isAdmin() || $this->isSalesperson();
    }

    /**
     * Check if user can edit email templates (accounts receivable or admin)
     */
    public function canEditEmailTemplates(): bool
    {
        return $this->isAdmin() || $this->role === self::ROLE_USER;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Get the user's Gmail token.
     */
    public function gmailToken(): HasOne
    {
        return $this->hasOne(UserGmailToken::class);
    }

    /**
     * Check if user has Gmail connected
     */
    public function hasGmailConnected(): bool
    {
        return $this->gmailToken()->exists();
    }
}
