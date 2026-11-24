<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'trading_name', 'email', 'phone', 'vat_number', 'registration_number',
    'payment_terms_days', 'address_line1', 'address_line2', 'city', 'province',
    'postal_code', 'country', 'notes', 'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }

    public function displayName(): string
    {
        return $this->trading_name ?: $this->name;
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            trim(implode(' ', array_filter([$this->city, $this->province, $this->postal_code]))),
            $this->country,
        ], fn (?string $line) => filled($line)));
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
