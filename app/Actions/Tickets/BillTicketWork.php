<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Actions\Billing\CreateInvoiceFromWork;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Billing\WorkBilling;
use Illuminate\Validation\ValidationException;

final class BillTicketWork
{
    public function __construct(
        private readonly CreateInvoiceFromWork $invoices,
        private readonly WorkBilling $billing,
    ) {}

    public function handle(User $actor, Ticket $ticket): Invoice
    {
        $ticket->loadMissing('client');
        $items = [];

        foreach ($ticket->timeEntries()->where('billable', true)->whereNull('invoice_line_id')->get() as $entry) {
            $suggestion = $this->billing->suggestion($entry);
            $items[] = [
                'include' => true,
                'type' => 'time',
                'id' => $entry->id,
                'description' => $suggestion['description'],
                'quantity' => $suggestion['quantity'],
                'unit_price' => $suggestion['unit_price'],
                'includes_vat' => false,
                'vat_code' => $suggestion['vat_code'],
            ];
        }

        foreach ($ticket->parts()->where('billable', true)->whereNull('invoice_line_id')->get() as $part) {
            $suggestion = $this->billing->partSuggestion($part);
            $items[] = [
                'include' => true,
                'type' => 'part',
                'id' => $part->id,
                'product_id' => $suggestion['product_id'],
                'description' => $suggestion['description'],
                'quantity' => $suggestion['quantity'],
                'unit_price' => $suggestion['unit_price'],
                'includes_vat' => $suggestion['includes_vat'],
                'vat_code' => $suggestion['vat_code'],
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'bill' => 'There is no unbilled time or parts on this ticket.',
            ]);
        }

        return $this->invoices->handle($actor, $ticket->client, $items, 'ticket', $ticket->id);
    }
}
