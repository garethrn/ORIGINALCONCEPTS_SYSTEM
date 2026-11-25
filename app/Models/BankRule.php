<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankRule extends Model
{
    protected $fillable = ['bank_account_id', 'contains', 'account_id', 'capital'];

    protected function casts(): array
    {
        return ['capital' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
