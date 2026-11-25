<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class CustomerStatement
{
    /**
     * @return array{opening_cents: int, closing_cents: int, rows: list<array{date: string, type: string, number: string, detail: string, debit_cents: int, credit_cents: int, balance_cents: int}>}
     */
    public function build(Client $client, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->toDateString();
        $end = $to->toDateString();
        $opening = $this->movementBefore($client, $start);
        $rows = $this->movements($client, $start, $end)
            ->sortBy([['date', 'asc'], ['sort', 'asc'], ['number', 'asc']])
            ->values();

        $balance = $opening;
        $printed = [];
        foreach ($rows as $row) {
            $balance += $row['debit_cents'] - $row['credit_cents'];
            $printed[] = [
                'date' => $row['date'],
                'type' => $row['type'],
                'number' => $row['number'],
                'detail' => $row['detail'],
                'debit_cents' => $row['debit_cents'],
                'credit_cents' => $row['credit_cents'],
                'balance_cents' => $balance,
            ];
        }

        return [
            'opening_cents' => $opening,
            'closing_cents' => $balance,
            'rows' => $printed,
        ];
    }

    private function movementBefore(Client $client, string $start): int
    {
        $invoices = (int) Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereDate('issue_date', '<', $start)
            ->sum('total_cents');
        $credits = (int) CreditNote::query()
            ->where('client_id', $client->id)
            ->where('status', CreditNoteStatus::Issued->value)
            ->whereDate('issue_date', '<', $start)
            ->sum('total_cents');
        $payments = (int) Payment::query()
            ->where('client_id', $client->id)
            ->whereDate('paid_on', '<', $start)
            ->sum('amount_cents');

        return $invoices - $credits - $payments;
    }

    /**
     * @return Collection<int, array{date: string, sort: int, type: string, number: string, detail: string, debit_cents: int, credit_cents: int}>
     */
    private function movements(Client $client, string $start, string $end): Collection
    {
        $rows = collect();
        Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereDate('issue_date', '>=', $start)
            ->whereDate('issue_date', '<=', $end)
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice) use ($rows) {
                $rows->push([
                    'date' => $invoice->issue_date->toDateString(),
                    'sort' => 1,
                    'type' => 'Invoice',
                    'number' => (string) $invoice->number,
                    'detail' => 'Tax invoice',
                    'debit_cents' => (int) $invoice->total_cents,
                    'credit_cents' => 0,
                ]);
            });
        CreditNote::query()
            ->where('client_id', $client->id)
            ->where('status', CreditNoteStatus::Issued->value)
            ->whereDate('issue_date', '>=', $start)
            ->whereDate('issue_date', '<=', $end)
            ->orderBy('id')
            ->get()
            ->each(function (CreditNote $note) use ($rows) {
                $rows->push([
                    'date' => $note->issue_date->toDateString(),
                    'sort' => 2,
                    'type' => 'Credit note',
                    'number' => (string) $note->number,
                    'detail' => 'Credit note',
                    'debit_cents' => 0,
                    'credit_cents' => (int) $note->total_cents,
                ]);
            });
        Payment::query()
            ->where('client_id', $client->id)
            ->whereDate('paid_on', '>=', $start)
            ->whereDate('paid_on', '<=', $end)
            ->orderBy('id')
            ->get()
            ->each(function (Payment $payment) use ($rows) {
                $rows->push([
                    'date' => $payment->paid_on->toDateString(),
                    'sort' => 3,
                    'type' => 'Payment',
                    'number' => (string) ($payment->reference ?: 'PMT-'.$payment->id),
                    'detail' => $payment->method->label(),
                    'debit_cents' => 0,
                    'credit_cents' => (int) $payment->amount_cents,
                ]);
            });

        return $rows;
    }
}
