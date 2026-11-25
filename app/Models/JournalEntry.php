<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = ['entry_date', 'memo', 'source_type', 'source_id', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isManual(): bool
    {
        return $this->source_type === null || $this->source_type === 'manual';
    }
}
