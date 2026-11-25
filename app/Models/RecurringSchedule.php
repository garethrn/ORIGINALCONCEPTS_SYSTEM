<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'name', 'frequency', 'next_on', 'ends_on', 'auto_issue', 'active', 'notes', 'created_by'])]
class RecurringSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'next_on' => 'date',
            'ends_on' => 'date',
            'auto_issue' => 'boolean',
            'active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringScheduleLine::class)->orderBy('sort_order');
    }
}
