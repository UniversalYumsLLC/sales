<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospect_id',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the prospect this product belongs to.
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
