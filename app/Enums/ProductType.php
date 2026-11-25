<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Stock = 'stock';
    case Service = 'service';
    case Labour = 'labour';
    case Subscription = 'subscription';
    case Print = 'print';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
