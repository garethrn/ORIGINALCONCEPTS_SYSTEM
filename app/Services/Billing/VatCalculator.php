<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\VatCode;
use InvalidArgumentException;

final class LineAmounts
{
    public function __construct(
        public readonly int $netCents,
        public readonly int $vatCents,
        public readonly int $totalCents,
        public readonly int $vatBasisPoints,
    ) {}
}

final class VatCalculator
{
    public function calculate(string $quantity, int $unitPriceCents, bool $includesVat, VatCode $vat, ?int $basisPoints = null): LineAmounts
    {
        $quantity = $this->normalizeQuantity($quantity);
        $basisPoints ??= $vat->basisPoints();
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('VAT rate cannot be negative.');
        }
        $extended = $this->roundHalfUp(bcmul((string) $unitPriceCents, $quantity, 6));

        if ($includesVat) {
            if ($basisPoints === 0) {
                return new LineAmounts($extended, 0, $extended, 0);
            }

            $vatCents = $this->roundHalfUp(bcdiv(
                bcmul((string) $extended, (string) $basisPoints, 6),
                (string) (10000 + $basisPoints),
                6,
            ));

            return new LineAmounts($extended - $vatCents, $vatCents, $extended, $basisPoints);
        }

        $vatCents = $basisPoints === 0
            ? 0
            : $this->roundHalfUp(bcdiv(bcmul((string) $extended, (string) $basisPoints, 6), '10000', 6));

        return new LineAmounts($extended, $vatCents, $extended + $vatCents, $basisPoints);
    }

    public function normalizeQuantity(string $quantity): string
    {
        $quantity = trim($quantity);
        if (! preg_match('/^\d+(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return bcadd($quantity, '0', 3);
    }

    private function roundHalfUp(string $number): int
    {
        $negative = bccomp($number, '0', 6) < 0;
        $absolute = $negative ? ltrim($number, '-') : $number;
        $rounded = (int) bcadd($absolute, '0.5', 0);

        return $negative ? -$rounded : $rounded;
    }
}
