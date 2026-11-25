<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;

final class QuotePackages
{
    public function syncTotals(Quote $quote): void
    {
        $quote->load(['lines', 'options']);
        $shared = $quote->lines->filter(fn (QuoteLine $line) => $line->quote_option_id === null && ! $line->is_optional);

        foreach ($quote->options as $option) {
            $package = $shared->concat($quote->lines->where('quote_option_id', $option->id));
            $option->total_cents = (int) $package->sum('total_cents');
            $option->save();
        }

        $chosen = $quote->selected_option_id
            ? $quote->options->firstWhere('id', $quote->selected_option_id)
            : $quote->options->sortBy('sort_order')->first();
        $headline = $this->selectedLines($quote, $chosen?->id);
        if ($quote->options->isEmpty()) {
            $headline = $quote->lines->filter(fn (QuoteLine $line) => ! $line->is_optional || $line->option_accepted);
        }

        $quote->subtotal_cents = (int) $headline->sum('net_cents');
        $quote->vat_cents = (int) $headline->sum('vat_cents');
        $quote->total_cents = (int) $headline->sum('total_cents');
        $quote->save();
    }

    public function requiresChoice(Quote $quote): bool
    {
        $quote->loadMissing('options');

        return $quote->options->isNotEmpty();
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    public function invoiceLines(Quote $quote): Collection
    {
        $quote->loadMissing(['lines', 'options']);
        if ($this->requiresChoice($quote) && $quote->selected_option_id === null) {
            return collect();
        }

        return $this->selectedLines($quote, $quote->selected_option_id);
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function selectedLines(Quote $quote, ?int $optionId): Collection
    {
        return $quote->lines->filter(function (QuoteLine $line) use ($optionId) {
            if ($line->quote_option_id !== null) {
                return $optionId !== null && (int) $line->quote_option_id === $optionId;
            }
            if ($line->is_optional) {
                return (bool) $line->option_accepted;
            }

            return true;
        })->values();
    }
}
