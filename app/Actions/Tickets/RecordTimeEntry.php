<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\VatCode;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\CompanyProfile;
use App\Support\Money;

final class RecordTimeEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CompanyProfile $company,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Ticket $ticket, array $data): TimeEntry
    {
        $rate = isset($data['hourly_rate']) && $data['hourly_rate'] !== ''
            ? Money::randsToCents((string) $data['hourly_rate'])
            : (int) $this->company->get('default_labour_rate_cents', '65000');

        $entry = TimeEntry::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'work_date' => $data['work_date'],
            'minutes' => (int) $data['minutes'],
            'description' => $data['description'],
            'billable' => (bool) ($data['billable'] ?? true),
            'hourly_rate_cents' => $rate,
            'vat_code' => VatCode::from($data['vat_code'] ?? VatCode::Standard->value),
        ]);

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'time',
            'note' => $entry->minutes.' minutes logged',
            'created_at' => now(),
        ]);
        $this->audit->log('ticket.time', $entry, null, ['minutes' => $entry->minutes, 'billable' => $entry->billable], $actor->id);

        return $entry;
    }
}
