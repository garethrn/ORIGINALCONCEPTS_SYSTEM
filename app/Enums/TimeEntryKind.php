<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeEntryKind: string
{
    case Labour = 'labour';
    case CallOut = 'call_out';
    case Travel = 'travel';

    public function label(): string
    {
        return match ($this) {
            self::Labour => 'Labour',
            self::CallOut => 'Call-out',
            self::Travel => 'Travel',
        };
    }
}
