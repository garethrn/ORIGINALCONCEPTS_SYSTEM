<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = ['supplier_id', 'supplier_reference', 'notes', 'expense_account_id', 'capital', 'created_by'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'capital' => 'boolean',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class)->orderBy('sort_order');
    }
}
