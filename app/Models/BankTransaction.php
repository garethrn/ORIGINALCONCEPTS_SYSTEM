<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id', 'posted_on', 'description', 'amount_cents', 'reference',
        'import_hash', 'journal_line_id', 'reconciliation_id',
    ];

    protected function casts(): array
    {
        return ['posted_on' => 'date'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }
}
