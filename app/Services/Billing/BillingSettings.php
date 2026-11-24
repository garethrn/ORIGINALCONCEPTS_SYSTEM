<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Client;

final class BillingSettings
{
    public function __construct(private readonly CompanyProfile $company) {}

    public function labourRateCents(?Client $client = null): int
    {
        return $client?->labour_rate_cents !== null
            ? (int) $client->labour_rate_cents
            : $this->cents('default_labour_rate_cents', 65000);
    }

    public function callOutFeeCents(?Client $client = null): int
    {
        return $client?->call_out_fee_cents !== null
            ? (int) $client->call_out_fee_cents
            : $this->cents('call_out_fee_cents', 45000);
    }

    public function travelRateCents(?Client $client = null): int
    {
        return $client?->travel_rate_cents !== null
            ? (int) $client->travel_rate_cents
            : $this->cents('travel_rate_cents', 650);
    }

    public function afterHoursMultiplier(): string
    {
        $value = $this->company->get('after_hours_multiplier', '1.5');

        return preg_match('/^\d+(\.\d{1,4})?$/', $value) === 1 ? $value : '1.5';
    }

    public function applyAfterHours(int $cents): int
    {
        $raw = bcmul((string) $cents, $this->afterHoursMultiplier(), 4);
        $negative = bccomp($raw, '0', 4) < 0;
        $rounded = (int) bcadd(ltrim($raw, '-'), '0.5', 0);

        return $negative ? -$rounded : $rounded;
    }

    public function minimumMinutes(): int
    {
        return max(0, (int) $this->company->get('minimum_billable_minutes', '30'));
    }

    public function roundingMinutes(): int
    {
        $step = (int) $this->company->get('rounding_minutes', '15');

        return in_array($step, [1, 5, 15, 30, 60], true) ? $step : 15;
    }

    public function standardVatBasisPoints(): int
    {
        $points = (int) $this->company->get('standard_vat_basis_points', '1500');

        return max(0, min(10000, $points));
    }

    public function showPricesExclVat(): bool
    {
        return $this->company->get('show_prices_excl_vat', '1') !== '0';
    }

    public function invoiceTimeLayout(): string
    {
        return $this->company->get('invoice_time_layout', 'summary') === 'detail' ? 'detail' : 'summary';
    }

    private function cents(string $key, int $default): int
    {
        $value = $this->company->get($key, (string) $default);

        return max(0, (int) $value);
    }
}
