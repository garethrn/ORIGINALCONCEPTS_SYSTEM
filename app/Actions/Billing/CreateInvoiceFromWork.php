<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\TimeEntryKind;
use App\Enums\VatCode;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PartUsed;
use App\Models\TicketEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\VatCalculator;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInvoiceFromWork
{
    public function __construct(
        private readonly SaveDraftInvoice $drafts,
        private readonly VatCalculator $vat,
        private readonly BillingSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(User $actor, Client $client, array $items, string $group, ?int $onlyTicketId = null): Invoice
    {
        if (! in_array($group, ['ticket', 'type'], true)) {
            throw ValidationException::withMessages([
                'group' => 'Choose whether to group lines by ticket or by type.',
            ]);
        }

        $selected = array_values(array_filter($items, fn (array $item) => ! empty($item['include'])));
        if ($selected === []) {
            throw ValidationException::withMessages([
                'items' => 'Select at least one unbilled entry.',
            ]);
        }

        return DB::transaction(function () use ($actor, $client, $selected, $group, $onlyTicketId) {
            $locked = [];
            foreach ($selected as $item) {
                $locked[] = $this->lockItem($client, $item, $onlyTicketId);
            }

            $groups = $this->group($locked, $group);
            $lines = array_map(fn (array $rows) => $this->toLine($rows, $group), $groups);
            $ticketIds = collect($locked)->map(fn (array $row) => (int) $row['model']->ticket_id)->unique()->values();

            $invoice = $this->drafts->handle($actor, [
                'client_id' => $client->id,
                'ticket_id' => $ticketIds->count() === 1 ? $ticketIds->first() : $onlyTicketId,
                'notes' => 'Billed from the ticket log.',
                'lines' => $lines,
            ]);

            foreach ($invoice->lines as $index => $line) {
                foreach ($groups[$index] as $row) {
                    $row['model']->invoice_line_id = $line->id;
                    $row['model']->save();
                }
            }

            foreach ($ticketIds as $ticketId) {
                TicketEvent::query()->create([
                    'ticket_id' => $ticketId,
                    'user_id' => $actor->id,
                    'action' => 'billed',
                    'note' => 'Selected work added to draft invoice '.$invoice->id,
                    'created_at' => now(),
                ]);
            }
            $this->audit->log('ticket.billed', $client, null, [
                'invoice_id' => $invoice->id,
                'ticket_ids' => $ticketIds->all(),
            ], $actor->id);

            return $invoice->load('lines', 'client');
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{model: Model, line: array<string, mixed>}
     */
    private function lockItem(Client $client, array $item, ?int $onlyTicketId): array
    {
        $type = (string) ($item['type'] ?? '');
        $model = $type === 'part'
            ? PartUsed::query()->whereKey($item['id'] ?? 0)->lockForUpdate()->first()
            : TimeEntry::query()->whereKey($item['id'] ?? 0)->lockForUpdate()->first();

        if (! $model instanceof TimeEntry && ! $model instanceof PartUsed) {
            throw ValidationException::withMessages([
                'items' => 'One of the selected entries could not be found.',
            ]);
        }

        $model->loadMissing('ticket');
        $ticket = $model->ticket;
        $alreadyBilled = $model->invoice_line_id !== null || ! $model->billable;
        $wrongClient = $ticket === null || (int) $ticket->client_id !== (int) $client->id;
        $wrongTicket = $onlyTicketId !== null && (int) $model->ticket_id !== $onlyTicketId;
        if ($alreadyBilled || $wrongClient || $wrongTicket) {
            throw ValidationException::withMessages([
                'items' => 'That work is no longer unbilled. Refresh the list and try again.',
            ]);
        }

        $quantity = (string) $item['quantity'];
        if (bccomp($quantity, '0', 3) !== 1) {
            throw ValidationException::withMessages([
                'items' => 'Quantity must be greater than zero.',
            ]);
        }

        return [
            'model' => $model,
            'line' => [
                'description' => trim((string) $item['description']),
                'quantity' => $quantity,
                'unit_price' => (string) $item['unit_price'],
                'includes_vat' => (bool) ($item['includes_vat'] ?? false),
                'vat_code' => (string) $item['vat_code'],
                'product_id' => $model instanceof PartUsed ? ($item['product_id'] ?? $model->product_id) : null,
            ],
        ];
    }

    /**
     * @param  list<array{model: Model, line: array<string, mixed>}>  $rows
     * @return list<list<array{model: Model, line: array<string, mixed>}>>
     */
    private function group(array $rows, string $group): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $buckets[$this->key($row, $group)][] = $row;
        }

        return array_values($buckets);
    }

    /**
     * @param  array{model: Model, line: array<string, mixed>}  $row
     */
    private function key(array $row, string $group): string
    {
        $model = $row['model'];
        $vat = (string) $row['line']['vat_code'].'|'.((bool) $row['line']['includes_vat'] ? '1' : '0');
        if ($model instanceof PartUsed) {
            return 'part-'.$model->id.'|'.$vat;
        }

        $kind = ($model->kind ?? TimeEntryKind::Labour)->value;
        $scope = $group === 'type' ? 'all' : 'ticket-'.$model->ticket_id;

        return $scope.'|'.$kind.'|'.$vat;
    }

    /**
     * @param  list<array{model: Model, line: array<string, mixed>}>  $rows
     * @return array<string, mixed>
     */
    private function toLine(array $rows, string $group): array
    {
        if (count($rows) === 1) {
            return $rows[0]['line'];
        }

        $prices = array_unique(array_map(fn (array $row) => (string) $row['line']['unit_price'], $rows));
        $first = $rows[0]['line'];
        $description = $this->describe($rows, $group);
        if (count($prices) === 1 && ! $first['includes_vat']) {
            $quantity = '0.000';
            foreach ($rows as $row) {
                $quantity = bcadd($quantity, (string) $row['line']['quantity'], 3);
            }

            return [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $prices[0],
                'includes_vat' => false,
                'vat_code' => $first['vat_code'],
            ];
        }

        $net = 0;
        foreach ($rows as $row) {
            $code = VatCode::from((string) $row['line']['vat_code']);
            $basis = $code === VatCode::Standard ? $this->settings->standardVatBasisPoints() : $code->basisPoints();
            $amounts = $this->vat->calculate(
                (string) $row['line']['quantity'],
                Money::randsToCents((string) $row['line']['unit_price']),
                (bool) $row['line']['includes_vat'],
                $code,
                $basis,
            );
            $net += $amounts->netCents;
        }

        return [
            'description' => $description,
            'quantity' => '1',
            'unit_price' => Money::centsToRands($net),
            'includes_vat' => false,
            'vat_code' => (string) $first['vat_code'],
        ];
    }

    /**
     * @param  list<array{model: Model, line: array<string, mixed>}>  $rows
     */
    private function describe(array $rows, string $group): string
    {
        $model = $rows[0]['model'];
        if (! $model instanceof TimeEntry) {
            return (string) $rows[0]['line']['description'];
        }

        $label = ($model->kind ?? TimeEntryKind::Labour)->label();
        if ($group === 'ticket') {
            $model->loadMissing('ticket');

            return $label.' · '.($model->ticket?->reference() ?? 'Ticket');
        }

        return $label;
    }
}
