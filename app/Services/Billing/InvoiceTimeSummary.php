<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\TimeEntryKind;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

final class InvoiceTimeSummary
{
    public function __construct(private readonly BillingSettings $settings) {}

    /**
     * @return list<array{label: string, detail: string}>
     */
    public function rows(Invoice $invoice): array
    {
        $lineIds = $invoice->lines->pluck('id');
        if ($lineIds->isEmpty()) {
            return [];
        }

        $entries = TimeEntry::query()
            ->whereIn('invoice_line_id', $lineIds)
            ->with('ticket')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();
        if ($entries->isEmpty()) {
            return [];
        }

        if ($this->settings->invoiceTimeLayout() === 'detail') {
            return $entries->map(function (TimeEntry $entry): array {
                $kind = ($entry->kind ?? TimeEntryKind::Labour)->label();
                if ($entry->after_hours) {
                    $kind .= ', after hours';
                }
                $measure = $entry->kind === TimeEntryKind::Travel
                    ? rtrim(rtrim((string) $entry->quantity, '0'), '.').' km'
                    : $entry->minutes.' min logged';

                return [
                    'label' => trim(($entry->ticket?->reference() ?? 'Ticket').' · '.optional($entry->work_date)->format('d M Y').' · '.$entry->description),
                    'detail' => $kind.' · '.$measure,
                ];
            })->all();
        }

        return $entries->groupBy('ticket_id')->map(function (Collection $group): array {
            $ticket = $group->first()?->ticket;
            $bits = [];
            foreach ($group->groupBy(fn (TimeEntry $entry) => ($entry->kind ?? TimeEntryKind::Labour)->value) as $kind => $rows) {
                $label = TimeEntryKind::from((string) $kind)->label();
                if ($kind === TimeEntryKind::Labour->value) {
                    $hours = bcadd(bcdiv((string) $rows->sum('minutes'), '60', 3), '0.005', 2);
                    $bits[] = $label.' '.$hours.' h logged';
                } elseif ($kind === TimeEntryKind::Travel->value) {
                    $km = $rows->sum(fn (TimeEntry $entry) => (float) $entry->quantity);
                    $bits[] = $label.' '.number_format($km, 1, '.', '').' km';
                } else {
                    $bits[] = $label.' × '.$rows->count();
                }
            }

            return [
                'label' => trim(($ticket?->reference() ?? 'Ticket').' · '.($ticket?->subject ?? '')),
                'detail' => implode(' · ', $bits),
            ];
        })->values()->all();
    }
}
