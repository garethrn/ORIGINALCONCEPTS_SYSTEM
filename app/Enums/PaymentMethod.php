<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Eft = 'eft';
    case Cash = 'cash';
    case Card = 'card';
    case PayFast = 'payfast';
    case Yoco = 'yoco';

    public function label(): string
    {
        return match ($this) {
            self::Eft => 'EFT',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::PayFast => 'PayFast',
            self::Yoco => 'Yoco',
        };
    }
}
