<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'contact_id', 'ticket_id', 'notes', 'valid_until', 'created_by'])]
class Quote extends Model
{
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === QuoteStatus::Draft;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === Role::Client) {
            $query->where('client_id', $user->client_id ?? 0)->where('status', '!=', QuoteStatus::Draft->value);
        }

        return $query;
    }
}
