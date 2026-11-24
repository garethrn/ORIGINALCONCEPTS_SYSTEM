<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'client_id', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token', 'totp_secret', 'recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->totp_confirmed_at !== null && filled($this->totp_secret);
    }
}
