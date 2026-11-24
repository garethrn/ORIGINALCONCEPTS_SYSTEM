<?php

declare(strict_types=1);

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
