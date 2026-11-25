<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimeEntryKind;
use App\Enums\VatCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id', 'user_id', 'work_date', 'minutes', 'description', 'billable',
    'hourly_rate_cents', 'vat_code', 'invoice_line_id', 'kind', 'after_hours', 'quantity',
])]
class TimeEntry extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'billable' => 'boolean',
            'after_hours' => 'boolean',
            'kind' => TimeEntryKind::class,
            'quantity' => 'decimal:3',
            'vat_code' => VatCode::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
