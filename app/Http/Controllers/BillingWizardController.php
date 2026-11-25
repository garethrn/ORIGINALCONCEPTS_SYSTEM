<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\CreateInvoiceFromWork;
use App\Enums\TimeEntryKind;
use App\Enums\VatCode;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PartUsed;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Services\Billing\WorkBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingWizardController extends Controller
{
    public function ticket(Ticket $ticket, WorkBilling $billing): View
    {
        $this->authorize('bill', $ticket);
        $ticket->loadMissing('client');

        return view('billing.wizard', $this->page($ticket->client, $billing, $ticket));
    }

    public function client(Client $client, WorkBilling $billing): View
    {
        $this->authorize('view', $client);
        $this->authorize('create', Invoice::class);

        return view('billing.wizard', $this->page($client, $billing, null));
    }

    public function storeTicket(Request $request, Ticket $ticket, CreateInvoiceFromWork $action): RedirectResponse
    {
        $this->authorize('bill', $ticket);

        return $this->store($request, $ticket->client, $action, $ticket->id);
    }

    public function storeClient(Request $request, Client $client, CreateInvoiceFromWork $action): RedirectResponse
    {
        $this->authorize('view', $client);
        $this->authorize('create', Invoice::class);

        return $this->store($request, $client, $action, null);
    }

    private function store(Request $request, Client $client, CreateInvoiceFromWork $action, ?int $ticketId): RedirectResponse
    {
        $data = $request->validate([
            'group' => ['required', 'in:ticket,type'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.include' => ['nullable', 'boolean'],
            'items.*.type' => ['required', 'in:time,part'],
            'items.*.id' => ['required', 'integer'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.unit_price' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'items.*.includes_vat' => ['nullable', 'boolean'],
            'items.*.vat_code' => ['required', Rule::enum(VatCode::class)],
            'items.*.product_id' => ['nullable', 'integer'],
        ]);

        foreach ($data['items'] as $index => $item) {
            $data['items'][$index]['include'] = $request->boolean('items.'.$index.'.include');
            $data['items'][$index]['includes_vat'] = $request->boolean('items.'.$index.'.includes_vat');
            if (($item['product_id'] ?? '') === '') {
                $data['items'][$index]['product_id'] = null;
            }
        }

        $invoice = $action->handle($request->user(), $client, $data['items'], $data['group'], $ticketId);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created from the selected work. It has no number until you issue it.');
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Client $client, WorkBilling $billing, ?Ticket $ticket): array
    {
        $time = TimeEntry::query()
            ->where('billable', true)
            ->whereNull('invoice_line_id')
            ->whereHas('ticket', function ($query) use ($client, $ticket) {
                $query->where('client_id', $client->id);
                if ($ticket) {
                    $query->whereKey($ticket->id);
                }
            })
            ->with('ticket')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $parts = PartUsed::query()
            ->where('billable', true)
            ->whereNull('invoice_line_id')
            ->whereHas('ticket', function ($query) use ($client, $ticket) {
                $query->where('client_id', $client->id);
                if ($ticket) {
                    $query->whereKey($ticket->id);
                }
            })
            ->with('ticket')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($time as $entry) {
            $suggestion = $billing->suggestion($entry);
            $kind = $entry->kind ?? TimeEntryKind::Labour;
            $measure = $kind === TimeEntryKind::Travel
                ? rtrim(rtrim((string) $entry->quantity, '0'), '.').' km'
                : ($kind === TimeEntryKind::CallOut ? 'Call-out' : $entry->minutes.' min logged');
            $rows[] = $suggestion + [
                'type' => 'time',
                'id' => $entry->id,
                'product_id' => null,
                'meta' => trim($entry->ticket?->reference().' · '.$kind->label().($entry->after_hours ? ' · after hours' : '').' · '.optional($entry->work_date)->format('d M Y').' · '.$measure),
            ];
        }
        foreach ($parts as $part) {
            $suggestion = $billing->partSuggestion($part);
            $rows[] = $suggestion + [
                'type' => 'part',
                'id' => $part->id,
                'meta' => trim($part->ticket?->reference().' · Parts / materials'),
            ];
        }

        return [
            'client' => $client,
            'ticket' => $ticket,
            'rows' => $rows,
            'action' => $ticket ? route('tickets.bill.store', $ticket) : route('clients.bill.store', $client),
        ];
    }
}
