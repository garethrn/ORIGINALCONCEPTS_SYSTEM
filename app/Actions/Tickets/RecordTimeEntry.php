<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\TimeEntryKind;
use App\Enums\VatCode;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingSettings;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class RecordTimeEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BillingSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Ticket $ticket, array $data): TimeEntry
    {
        $kind = $data['kind'] instanceof TimeEntryKind
            ? $data['kind']
            : TimeEntryKind::from((string) ($data['kind'] ?? TimeEntryKind::Labour->value));
        $minutes = (int) ($data['minutes'] ?? 0);
        if ($kind === TimeEntryKind::Labour && $minutes < 1) {
            throw ValidationException::withMessages([
                'minutes' => 'Labour needs at least one minute.',
            ]);
        }
        if ($kind === TimeEntryKind::Travel && bccomp((string) ($data['quantity'] ?? '0'), '0', 3) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Travel needs the kilometres.',
            ]);
        }

        $ticket->loadMissing('client');
        $rate = isset($data['hourly_rate']) && $data['hourly_rate'] !== ''
            ? Money::randsToCents((string) $data['hourly_rate'])
            : match ($kind) {
                TimeEntryKind::Labour => $this->settings->labourRateCents($ticket->client),
                TimeEntryKind::CallOut => $this->settings->callOutFeeCents($ticket->client),
                TimeEntryKind::Travel => $this->settings->travelRateCents($ticket->client),
            };

        $entry = TimeEntry::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'work_date' => $data['work_date'],
            'minutes' => $minutes,
            'kind' => $kind,
            'after_hours' => $kind === TimeEntryKind::Labour && (bool) ($data['after_hours'] ?? false),
            'quantity' => $kind === TimeEntryKind::Travel ? $data['quantity'] : null,
            'description' => $data['description'],
            'billable' => (bool) ($data['billable'] ?? true),
            'hourly_rate_cents' => $rate,
            'vat_code' => VatCode::from($data['vat_code'] ?? VatCode::Standard->value),
        ]);

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'time',
            'note' => $kind->label().' logged',
            'created_at' => now(),
        ]);
        $this->audit->log('ticket.time', $entry, null, [
            'kind' => $kind->value,
            'minutes' => $entry->minutes,
            'billable' => $entry->billable,
        ], $actor->id);

        return $entry;
    }
}
