<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'vat_number', 'payment_terms_days', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'payment_terms_days' => 'integer'];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
