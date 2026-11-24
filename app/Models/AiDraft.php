<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDraft extends Model
{
    protected $fillable = [
        'user_id', 'type', 'payload', 'preview', 'status', 'expires_at',
        'confirmed_at', 'result_type', 'result_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
