<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Actions\Billing\SaveDraftInvoice;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillTicketWork
{
    public function __construct(
        private readonly SaveDraftInvoice $drafts,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Ticket $ticket): Invoice
    {
        return DB::transaction(function () use ($actor, $ticket) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $times = $ticket->timeEntries()->where('billable', true)->whereNull('invoice_line_id')->get();
            $parts = $ticket->parts()->where('billable', true)->whereNull('invoice_line_id')->get();
            if ($times->isEmpty() && $parts->isEmpty()) {
                throw ValidationException::withMessages([
                    'bill' => 'There is no unbilled time or parts on this ticket.',
                ]);
            }

            $lines = [];
            foreach ($times as $entry) {
                $hours = bcdiv((string) $entry->minutes, '60', 3);
                $lines[] = [
                    'source' => $entry,
                    'line' => [
                        'description' => 'Time: '.$entry->description,
                        'quantity' => $hours,
                        'unit_price' => Money::centsToRands((int) $entry->hourly_rate_cents),
                        'includes_vat' => false,
                        'vat_code' => $entry->vat_code->value,
                    ],
                ];
            }
            foreach ($parts as $part) {
                $lines[] = [
                    'source' => $part,
                    'line' => [
                        'product_id' => $part->product_id,
                        'description' => $part->description,
                        'quantity' => (string) $part->quantity,
                        'unit_price' => Money::centsToRands((int) $part->unit_price_cents),
                        'includes_vat' => (bool) $part->price_includes_vat,
                        'vat_code' => $part->vat_code->value,
                    ],
                ];
            }

            $invoice = $this->drafts->handle($actor, [
                'client_id' => $ticket->client_id,
                'contact_id' => $ticket->contact_id,
                'ticket_id' => $ticket->id,
                'notes' => 'Work from ticket '.$ticket->reference(),
                'lines' => array_map(fn ($row) => $row['line'], $lines),
            ]);

            foreach ($invoice->lines as $index => $line) {
                $source = $lines[$index]['source'];
                $source->invoice_line_id = $line->id;
                $source->save();
            }

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'action' => 'billed',
                'note' => 'Unbilled work added to draft invoice '.$invoice->id,
                'created_at' => now(),
            ]);
            $this->audit->log('ticket.billed', $ticket, null, ['invoice_id' => $invoice->id], $actor->id);

            return $invoice;
        });
    }
}
