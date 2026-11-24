<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_id', 'client_id', 'notes', 'created_by'])]
class CreditNote extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'issue_date' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === CreditNoteStatus::Draft;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('sort_order');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === Role::Client) {
            $query->where('client_id', $user->client_id ?? 0)->where('status', '!=', CreditNoteStatus::Draft->value);
        }

        return $query;
    }
}
