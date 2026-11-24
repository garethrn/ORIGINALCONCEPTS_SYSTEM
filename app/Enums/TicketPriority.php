<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 8,
            self::Medium => 24,
            self::Low => 72,
        };
    }
}
