<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Enums\InvoiceStatus;
use App\Models\BankRule;
use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;

final class TransactionMatcher
{
    /**
     * @return array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}
     */
    public function suggest(BankTransaction $transaction): array
    {
        $text = strtoupper($transaction->description.' '.$transaction->reference);
        $amount = abs((int) $transaction->amount_cents);
        if ($transaction->amount_cents > 0) {
            return $this->moneyIn($text, $amount);
        }
        if ($transaction->amount_cents < 0) {
            return $this->moneyOut($text, $amount);
        }

        return $this->empty();
    }

    /**
     * @return array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}
     */
    private function moneyIn(string $text, int $amount): array
    {
        if (preg_match('/INV-\d+/', $text, $match) === 1) {
            $invoice = Invoice::query()->where('number', $match[0])->first();
            if ($invoice !== null && $invoice->balanceCents() > 0) {
                $exact = $invoice->balanceCents() === $amount;

                return [
                    'confidence' => $exact ? 98 : 70,
                    'label' => $invoice->number.' '.$invoice->client?->displayName(),
                    'invoice_ids' => [$invoice->id],
                    'bill_id' => null,
                    'rule_id' => null,
                ];
            }
        }

        foreach (Client::query()->orderBy('name')->get() as $client) {
            $name = strtoupper($client->name);
            if (strlen($name) < 4 || ! str_contains($text, $name)) {
                continue;
            }
            $matches = $this->openInvoices($amount)->where('client_id', $client->id)->values();
            if ($matches->count() === 1) {
                return $this->invoices($matches, 92, $client->displayName());
            }
            if ($matches->count() > 1) {
                return $this->invoices($matches, 55, $client->displayName());
            }
        }

        $exact = $this->openInvoices($amount);
        if ($exact->count() === 1) {
            return $this->invoices($exact, 85, 'Exact amount');
        }
        if ($exact->count() > 1) {
            return $this->invoices($exact, 40, 'Several invoices share this amount');
        }

        return $this->empty();
    }

    /**
     * @return array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}
     */
    private function moneyOut(string $text, int $amount): array
    {
        $rule = BankRule::query()->get()->first(fn (BankRule $rule) => str_contains(strtolower($text), strtolower($rule->contains)));
        $bills = Bill::query()->with('supplier')->where('status', 'open')->get()
            ->filter(fn (Bill $bill) => (int) $bill->total_cents === $amount);
        $named = $bills->filter(fn (Bill $bill) => strlen((string) $bill->supplier?->name) >= 4 && str_contains($text, strtoupper((string) $bill->supplier?->name)));
        if ($named->count() === 1) {
            $bill = $named->first();

            return [
                'confidence' => 92,
                'label' => $bill->number.' '.$bill->supplier?->name,
                'invoice_ids' => [],
                'bill_id' => $bill->id,
                'rule_id' => $rule?->id,
            ];
        }
        if ($bills->count() === 1) {
            $bill = $bills->first();

            return [
                'confidence' => 80,
                'label' => $bill->number.' exact amount',
                'invoice_ids' => [],
                'bill_id' => $bill->id,
                'rule_id' => $rule?->id,
            ];
        }
        $orders = PurchaseOrder::query()->with('supplier')->where('status', 'issued')->get()
            ->filter(fn (PurchaseOrder $order) => (int) $order->total_cents === $amount);
        if ($orders->count() === 1) {
            $order = $orders->first();

            return [
                'confidence' => 75,
                'label' => $order->number.' purchase order',
                'invoice_ids' => [],
                'bill_id' => null,
                'rule_id' => $rule?->id,
            ];
        }
        if ($rule !== null) {
            return [
                'confidence' => 88,
                'label' => 'Bank rule: '.$rule->contains,
                'invoice_ids' => [],
                'bill_id' => null,
                'rule_id' => $rule->id,
            ];
        }

        return $this->empty();
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openInvoices(int $amount): Collection
    {
        return Invoice::query()->with('client')->where('status', '!=', InvoiceStatus::Draft->value)->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() === $amount)
            ->values();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}
     */
    private function invoices(Collection $invoices, int $confidence, string $label): array
    {
        return [
            'confidence' => $confidence,
            'label' => $label.' · '.$invoices->map(fn (Invoice $invoice) => $invoice->number)->implode(', '),
            'invoice_ids' => $invoices->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'bill_id' => null,
            'rule_id' => null,
        ];
    }

    /**
     * @return array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}
     */
    private function empty(): array
    {
        return ['confidence' => 0, 'label' => '', 'invoice_ids' => [], 'bill_id' => null, 'rule_id' => null];
    }
}
