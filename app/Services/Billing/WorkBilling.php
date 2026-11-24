<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\TimeEntryKind;
use App\Models\PartUsed;
use App\Models\TimeEntry;
use App\Support\Money;

final class WorkBilling
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function billableMinutes(int $minutes): int
    {
        $step = max(1, $this->settings->roundingMinutes());
        $minimum = $this->settings->minimumMinutes();
        if ($minutes <= 0) {
            return $minimum;
        }

        $rounded = (int) (ceil($minutes / $step) * $step);

        return max($minimum, $rounded);
    }

    /**
     * @return array{description: string, quantity: string, unit_price: string, includes_vat: bool, vat_code: string}
     */
    public function suggestion(TimeEntry $entry): array
    {
        $kind = $entry->kind ?? TimeEntryKind::Labour;
        $rate = (int) $entry->hourly_rate_cents;
        if ($kind === TimeEntryKind::Labour && $entry->after_hours) {
            $rate = $this->settings->applyAfterHours($rate);
        }

        $quantity = match ($kind) {
            TimeEntryKind::Labour => bcdiv((string) $this->billableMinutes((int) $entry->minutes), '60', 3),
            TimeEntryKind::CallOut => '1.000',
            TimeEntryKind::Travel => bcadd((string) ($entry->quantity ?? '0'), '0', 3),
        };

        return [
            'description' => (string) $entry->description,
            'quantity' => $quantity,
            'unit_price' => Money::centsToRands($rate),
            'includes_vat' => false,
            'vat_code' => $entry->vat_code->value,
        ];
    }

    /**
     * @return array{description: string, quantity: string, unit_price: string, includes_vat: bool, vat_code: string, product_id: int|null}
     */
    public function partSuggestion(PartUsed $part): array
    {
        return [
            'description' => (string) $part->description,
            'quantity' => bcadd((string) $part->quantity, '0', 3),
            'unit_price' => Money::centsToRands((int) $part->unit_price_cents),
            'includes_vat' => (bool) $part->price_includes_vat,
            'vat_code' => $part->vat_code->value,
            'product_id' => $part->product_id,
        ];
    }
}
