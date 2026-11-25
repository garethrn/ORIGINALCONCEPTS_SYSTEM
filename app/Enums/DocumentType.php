<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Bill = 'bill';
    case Expense = 'expense';

    public function prefix(): string
    {
        return match ($this) {
            self::Quote => 'QUO',
            self::Invoice => 'INV',
            self::CreditNote => 'CN',
            self::Bill => 'BILL',
            self::Expense => 'EXP',
        };
    }
}
