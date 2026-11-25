<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'amount_cents', 'paid_on', 'method', 'reference', 'notes', 'created_by'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function unallocatedCents(): int
    {
        return (int) $this->amount_cents - (int) $this->allocations()->sum('amount_cents');
    }
}
