<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\VatCode;
use App\Support\Money;

final class DocumentLineWriter
{
    public function __construct(private readonly VatCalculator $vat) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, subtotal_cents: int, vat_cents: int, total_cents: int}
     */
    public function build(array $lines): array
    {
        $written = [];
        $net = 0;
        $vat = 0;
        $total = 0;

        foreach (array_values($lines) as $index => $line) {
            $code = $line['vat_code'] instanceof VatCode ? $line['vat_code'] : VatCode::from((string) $line['vat_code']);
            $basisPoints = $code === VatCode::Standard
                ? app(BillingSettings::class)->standardVatBasisPoints()
                : $code->basisPoints();
            $amounts = $this->vat->calculate(
                (string) $line['quantity'],
                Money::randsToCents((string) $line['unit_price']),
                (bool) $line['includes_vat'],
                $code,
                $basisPoints,
            );

            $written[] = [
                'source_id' => isset($line['id']) && $line['id'] !== '' ? (int) $line['id'] : null,
                'product_id' => $line['product_id'] ?? null,
                'description' => trim((string) $line['description']),
                'quantity' => $this->vat->normalizeQuantity((string) $line['quantity']),
                'unit_price_cents' => Money::randsToCents((string) $line['unit_price']),
                'price_includes_vat' => (bool) $line['includes_vat'],
                'vat_code' => $code->value,
                'vat_basis_points' => $amounts->vatBasisPoints,
                'net_cents' => $amounts->netCents,
                'vat_cents' => $amounts->vatCents,
                'total_cents' => $amounts->totalCents,
                'sort_order' => $index,
            ];
            $net += $amounts->netCents;
            $vat += $amounts->vatCents;
            $total += $amounts->totalCents;
        }

        return [
            'lines' => $written,
            'subtotal_cents' => $net,
            'vat_cents' => $vat,
            'total_cents' => $total,
        ];
    }
}
