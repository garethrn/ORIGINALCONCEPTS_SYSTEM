<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Services\Billing\InvoiceWorkLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'contact_id', 'ticket_id', 'quote_id', 'notes', 'created_by'])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'overdue_reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice): void {
            app(InvoiceWorkLinks::class)->release($invoice->lines()->pluck('id'));
        });
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDraft()
            && $this->due_date !== null
            && $this->due_date->isBefore(today())
            && $this->balanceCents() > 0;
    }

    public function balanceCents(): int
    {
        if (array_key_exists('paid_cents', $this->attributes) && array_key_exists('credited_cents', $this->attributes)) {
            return (int) $this->total_cents - (int) $this->attributes['paid_cents'] - (int) $this->attributes['credited_cents'];
        }

        $paid = (int) $this->allocations()->sum('amount_cents');
        $credited = (int) $this->creditNotes()->where('status', CreditNoteStatus::Issued->value)->sum('total_cents');

        return (int) $this->total_cents - $paid - $credited;
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === Role::Client) {
            $query->where('client_id', $user->client_id ?? 0)->where('status', '!=', InvoiceStatus::Draft->value);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status?->value,
            'total_cents' => $this->total_cents,
            'client_id' => $this->client_id,
        ];
    }
}
