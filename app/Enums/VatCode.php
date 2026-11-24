<?php

declare(strict_types=1);

namespace App\Enums;

enum VatCode: string
{
    case Standard = 'standard';
    case Zero = 'zero';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard (15%)',
            self::Zero => 'Zero-rated',
            self::Exempt => 'Exempt',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Standard => '15%',
            self::Zero => '0% zero-rated',
            self::Exempt => 'Exempt',
        };
    }

    public function basisPoints(): int
    {
        return $this === self::Standard ? 1500 : 0;
    }
}
