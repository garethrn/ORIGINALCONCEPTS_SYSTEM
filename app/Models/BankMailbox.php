<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankMailbox extends Model
{
    protected $fillable = [
        'name', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
        'imap_folder', 'forwarding_address', 'allowed_senders', 'auto_allocate', 'confidence_threshold', 'active',
    ];

    protected function casts(): array
    {
        return [
            'imap_password' => 'encrypted',
            'auto_allocate' => 'boolean',
            'active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function senderAllowList(): array
    {
        $lines = preg_split('/\r\n|\n|,/', (string) $this->allowed_senders) ?: [];

        return array_values(array_filter(array_map(fn ($line) => strtolower(trim($line)), $lines)));
    }
}
