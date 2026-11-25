<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankEmail extends Model
{
    protected $fillable = [
        'bank_mailbox_id', 'message_id', 'from_address', 'subject', 'received_at', 'raw_body', 'body_hash', 'status',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(BankMailbox::class, 'bank_mailbox_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
