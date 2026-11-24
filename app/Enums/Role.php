<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Finance = 'finance';
    case Manager = 'manager';
    case Technician = 'technician';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Finance => 'Finance',
            self::Manager => 'Manager',
            self::Technician => 'Technician',
            self::Client => 'Client',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageSettings(): bool
    {
        return $this === self::Admin;
    }

    public function canBill(): bool
    {
        return in_array($this, [self::Admin, self::Finance, self::Manager], true);
    }

    public function canTakePayment(): bool
    {
        return in_array($this, [self::Admin, self::Finance], true);
    }

    public function canIssueCredit(): bool
    {
        return in_array($this, [self::Admin, self::Finance], true);
    }

    public function canManageTickets(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Technician], true);
    }

    public function canManageClients(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Finance], true);
    }

    public function canManageProducts(): bool
    {
        return in_array($this, [self::Admin, self::Finance, self::Manager], true);
    }

    public function canUseAssistant(): bool
    {
        return $this->isStaff();
    }
}
