<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Role;
use App\Models\RecurringSchedule;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class RunRecurringSchedules
{
    public function __construct(
        private readonly SaveDraftInvoice $drafts,
        private readonly IssueInvoice $issue,
    ) {}

    public function handle(): int
    {
        $created = 0;
        $schedules = RecurringSchedule::query()
            ->where('active', true)
            ->whereDate('next_on', '<=', today())
            ->with('lines')
            ->get();

        foreach ($schedules as $schedule) {
            $created += $this->run($schedule);
        }

        return $created;
    }

    public function run(RecurringSchedule $schedule): int
    {
        $schedule->loadMissing('lines');
        $actor = User::query()->find($schedule->created_by)
            ?? User::query()->where('role', Role::Admin->value)->first();
        if ($actor === null || $schedule->lines->isEmpty()) {
            return 0;
        }

        $created = 0;
        for ($guard = 0; $guard < 24; $guard++) {
            if (! $schedule->active || $schedule->next_on->gt(today())) {
                break;
            }
            if ($schedule->ends_on !== null && $schedule->next_on->gt($schedule->ends_on)) {
                $schedule->active = false;
                $schedule->save();
                break;
            }

            $invoice = $this->drafts->handle($actor, [
                'client_id' => $schedule->client_id,
                'notes' => 'Recurring: '.$schedule->name,
                'lines' => $schedule->lines->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => Money::centsToRands((int) $line->unit_price_cents),
                    'includes_vat' => (bool) $line->price_includes_vat,
                    'vat_code' => $line->vat_code->value,
                ])->all(),
            ]);

            if ($schedule->auto_issue) {
                try {
                    $this->issue->handle($actor, $invoice);
                } catch (ValidationException) {
                    // A missing VAT number leaves the invoice as a draft instead of stopping the schedule.
                }
            }

            $schedule->last_run_at = now();
            $schedule->next_on = $schedule->frequency->advance($schedule->next_on);
            if ($schedule->ends_on !== null && $schedule->next_on->gt($schedule->ends_on)) {
                $schedule->active = false;
            }
            $schedule->save();
            $created++;
        }

        return $created;
    }
}
