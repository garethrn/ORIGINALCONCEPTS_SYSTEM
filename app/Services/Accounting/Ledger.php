<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\BillingSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Ledger
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function postInvoice(Invoice $invoice, ?User $actor = null): JournalEntry
    {
        $invoice->loadMissing('client');
        $vatRole = $this->settings->vatBasis() === 'cash' ? 'vat_output_deferred' : 'vat_output';

        return $this->post('invoice', $invoice->id, $invoice->issue_date->toDateString(), 'Invoice '.$invoice->number, [
            $this->amount(Account::forRole('receivable'), (int) $invoice->total_cents, true, $invoice->client_id),
            $this->amount(Account::forRole('sales'), (int) $invoice->subtotal_cents, false, $invoice->client_id),
            $this->amount(Account::forRole($vatRole), (int) $invoice->vat_cents, false, $invoice->client_id),
        ], $actor?->id);
    }

    public function postCreditNote(CreditNote $creditNote, ?User $actor = null): JournalEntry
    {
        $creditNote->loadMissing('invoice');
        $invoice = $creditNote->invoice;
        $output = (int) $creditNote->vat_cents;
        $deferred = 0;
        if ($this->settings->vatBasis() === 'cash' && $invoice !== null && (int) $invoice->total_cents !== 0) {
            $paid = (int) $invoice->allocations()->sum('amount_cents');
            $output = (int) round($creditNote->vat_cents * ($paid / $invoice->total_cents));
            $deferred = (int) $creditNote->vat_cents - $output;
        } elseif ($this->settings->vatBasis() === 'cash') {
            $deferred = $output;
            $output = 0;
        }

        return $this->post('credit_note', $creditNote->id, $creditNote->issue_date->toDateString(), 'Credit note '.$creditNote->number, [
            $this->amount(Account::forRole('sales'), (int) $creditNote->subtotal_cents, true, $creditNote->client_id),
            $this->amount(Account::forRole('vat_output'), $output, true, $creditNote->client_id),
            $this->amount(Account::forRole('vat_output_deferred'), $deferred, true, $creditNote->client_id),
            $this->amount(Account::forRole('receivable'), (int) $creditNote->total_cents, false, $creditNote->client_id),
        ], $actor?->id);
    }

    public function postPayment(Payment $payment, ?User $actor = null): JournalEntry
    {
        $payment->loadMissing('allocations.invoice');
        $bank = Account::forRole('bank');
        $lines = [
            $this->amount($bank, (int) $payment->amount_cents, true, $payment->client_id),
            $this->amount(Account::forRole('receivable'), (int) $payment->amount_cents, false, $payment->client_id),
        ];
        if ($this->settings->vatBasis() === 'cash') {
            foreach ($payment->allocations as $allocation) {
                $invoice = $allocation->invoice;
                if ($invoice === null || (int) $invoice->total_cents === 0 || (int) $invoice->vat_cents === 0) {
                    continue;
                }
                $portion = (int) round($allocation->amount_cents * ($invoice->vat_cents / $invoice->total_cents));
                $lines[] = $this->amount(Account::forRole('vat_output_deferred'), $portion, true, $payment->client_id);
                $lines[] = $this->amount(Account::forRole('vat_output'), $portion, false, $payment->client_id);
            }
        }

        return $this->post('payment', $payment->id, $payment->paid_on->toDateString(), 'Payment '.($payment->reference ?: $payment->id), $lines, $actor?->id);
    }

    public function postBill(Bill $bill, ?User $actor = null): JournalEntry
    {
        $expense = $bill->expenseAccount ?? Account::forRole($bill->capital ? 'purchases' : 'expense');
        $vatRole = $this->settings->vatBasis() === 'cash' ? 'vat_input_deferred' : 'vat_input';

        return $this->post('bill', $bill->id, $bill->issue_date->toDateString(), 'Bill '.$bill->number, [
            $this->amount($expense, (int) $bill->subtotal_cents, true, null, $bill->supplier_id),
            $this->amount(Account::forRole($vatRole), (int) $bill->vat_cents, true, null, $bill->supplier_id),
            $this->amount(Account::forRole('payable'), (int) $bill->total_cents, false, null, $bill->supplier_id),
        ], $actor?->id);
    }

    public function postExpense(Expense $expense, ?User $actor = null): JournalEntry
    {
        $bank = $expense->bankAccount?->account ?? Account::forRole('bank');
        $vatRole = $this->settings->vatBasis() === 'cash' ? 'vat_input' : 'vat_input';

        return $this->post('expense', $expense->id, $expense->spent_on->toDateString(), 'Expense '.$expense->number, [
            $this->amount($expense->account, (int) $expense->net_cents, true, null, $expense->supplier_id),
            $this->amount(Account::forRole($vatRole), (int) $expense->vat_cents, true, null, $expense->supplier_id),
            $this->amount($bank, (int) $expense->total_cents, false, null, $expense->supplier_id),
        ], $actor?->id);
    }

    /**
     * @param  list<array{account_id: int, debit_cents: int, credit_cents: int, description?: string|null, client_id?: int|null, supplier_id?: int|null}|null>  $lines
     */
    public function postManual(string $date, string $memo, array $lines, ?int $userId): JournalEntry
    {
        return $this->post('manual', null, $date, $memo, $lines, $userId);
    }

    /**
     * @param  list<array{account_id: int, debit_cents: int, credit_cents: int, description?: string|null, client_id?: int|null, supplier_id?: int|null}|null>  $lines
     */
    public function post(?string $sourceType, ?int $sourceId, string $date, string $memo, array $lines, ?int $userId): JournalEntry
    {
        $lines = array_values(array_filter($lines));
        $debit = array_sum(array_column($lines, 'debit_cents'));
        $credit = array_sum(array_column($lines, 'credit_cents'));
        if ($debit !== $credit) {
            throw new InvalidArgumentException('Journal does not balance: debit '.$debit.' credit '.$credit.'.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('A journal needs at least one line.');
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $date, $memo, $lines, $userId, $debit) {
            $entry = null;
            if ($sourceType !== null && $sourceType !== 'manual' && $sourceId !== null) {
                $entry = JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            }
            if ($entry === null) {
                $entry = new JournalEntry([
                    'source_type' => $sourceType === 'manual' ? 'manual' : $sourceType,
                    'source_id' => $sourceId,
                    'created_by' => $userId,
                ]);
            }
            $entry->entry_date = $date;
            $entry->memo = $memo;
            $entry->save();
            if ($sourceType === 'manual' && $entry->source_id === null) {
                $entry->source_id = $entry->id;
                $entry->save();
            }
            $entry->lines()->delete();
            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            if ((int) $entry->lines()->sum('debit_cents') !== $debit) {
                throw new InvalidArgumentException('Journal lines were not saved.');
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * @return array{account_id: int, debit_cents: int, credit_cents: int, description: ?string, client_id: ?int, supplier_id: ?int}|null
     */
    private function amount(Account $account, int $signed, bool $debitWhenPositive, ?int $clientId = null, ?int $supplierId = null, ?string $description = null): ?array
    {
        if ($signed === 0) {
            return null;
        }
        $debit = $debitWhenPositive ? $signed : -$signed;

        return [
            'account_id' => $account->id,
            'debit_cents' => $debit > 0 ? $debit : 0,
            'credit_cents' => $debit < 0 ? abs($debit) : 0,
            'description' => $description,
            'client_id' => $clientId,
            'supplier_id' => $supplierId,
        ];
    }
}
