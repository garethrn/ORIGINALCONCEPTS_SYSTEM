<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function format(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $rands = intdiv($cents, 100);
        $frac = $cents % 100;

        return ($negative ? '-' : '').'R '.number_format($rands, 0, '.', ',').'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);
    }

    public static function randsToCents(string $rands): int
    {
        $rands = trim($rands);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $rands)) {
            throw new InvalidArgumentException('Amount must be a rand value with up to two decimals.');
        }

        [$whole, $frac] = array_pad(explode('.', $rands, 2), 2, '0');
        $frac = str_pad(substr($frac, 0, 2), 2, '0');

        return ((int) $whole) * 100 + (int) $frac;
    }

    public static function centsToRands(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
