<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Actions\Billing\InvoiceSettlement;
use App\Actions\Billing\RecordPayment;
use App\Enums\Role;
use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\Ledger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransactionReviewer
{
    public function __construct(
        private readonly RecordPayment $payments,
        private readonly InvoiceSettlement $settlement,
        private readonly Ledger $ledger,
    ) {}

    /**
     * @param  list<int>  $invoiceIds
     * @param  array<int, int>|null  $amountsByInvoice
     */
    public function allocateInvoices(BankTransaction $transaction, array $invoiceIds, ?User $actor, ?array $amountsByInvoice = null): Payment
    {
        return DB::transaction(function () use ($transaction, $invoiceIds, $actor, $amountsByInvoice) {
            $invoices = Invoice::query()->whereIn('id', $invoiceIds)->lockForUpdate()->get();
            if ($invoices->isEmpty()) {
                throw ValidationException::withMessages(['invoice' => 'Choose an open invoice.']);
            }
            $clientId = (int) $invoices->first()->client_id;
            if ($invoices->contains(fn (Invoice $invoice) => (int) $invoice->client_id !== $clientId)) {
                throw ValidationException::withMessages(['invoice' => 'A split has to stay with one customer.']);
            }
            $remaining = abs((int) $transaction->amount_cents);
            $allocations = [];
            foreach ($invoices as $invoice) {
                $slice = $amountsByInvoice[$invoice->id] ?? min($remaining, $invoice->balanceCents());
                if ($slice <= 0) {
                    continue;
                }
                if ($slice > $invoice->balanceCents()) {
                    throw ValidationException::withMessages(['amount' => 'An allocation is larger than the invoice balance.']);
                }
                $allocations[] = ['invoice_id' => $invoice->id, 'amount' => Money::centsToRands($slice)];
                $remaining -= $slice;
            }
            if ($allocations === [] || $remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'The allocations have to add up to the bank amount.']);
            }
            $actor ??= User::query()->where('role', Role::Admin->value)->firstOrFail();
            $payment = $this->payments->handle($actor, [
                'client_id' => $clientId,
                'amount' => Money::centsToRands(abs((int) $transaction->amount_cents)),
                'paid_on' => $transaction->posted_on->toDateString(),
                'method' => 'eft',
                'reference' => $transaction->reference ?: 'Bank feed',
                'allocations' => $allocations,
            ]);
            $this->markAllocated($transaction, $payment);

            return $payment;
        });
    }

    public function payBill(BankTransaction $transaction, Bill $bill): void
    {
        if ((int) $bill->total_cents !== abs((int) $transaction->amount_cents) || $bill->status !== 'open') {
            throw ValidationException::withMessages(['bill' => 'The bill amount has to match this transaction.']);
        }
        $bank = $transaction->bankAccount->account;
        $entry = $this->ledger->post('supplier_payment', $bill->id, $transaction->posted_on->toDateString(), 'Payment of '.$bill->number, [
            [
                'account_id' => Account::forRole('payable')->id,
                'debit_cents' => (int) $bill->total_cents,
                'credit_cents' => 0,
                'description' => $bill->number,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ],
            [
                'account_id' => $bank->id,
                'debit_cents' => 0,
                'credit_cents' => (int) $bill->total_cents,
                'description' => $bill->number,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ],
        ], null);
        $bill->status = 'paid';
        $bill->save();
        $line = $entry->lines->firstWhere('account_id', $bank->id);
        $transaction->journal_line_id = $line?->id;
        $transaction->review_status = 'allocated';
        $transaction->save();
    }

    public function assignExpense(BankTransaction $transaction, int $accountId): void
    {
        $amount = abs((int) $transaction->amount_cents);
        $bankId = (int) $transaction->bankAccount->account_id;
        $entry = $this->ledger->post('bank_review', $transaction->id, $transaction->posted_on->toDateString(), $transaction->description, [
            [
                'account_id' => $accountId,
                'debit_cents' => $transaction->amount_cents < 0 ? $amount : 0,
                'credit_cents' => $transaction->amount_cents > 0 ? $amount : 0,
                'description' => $transaction->description,
                'client_id' => null,
                'supplier_id' => null,
            ],
            [
                'account_id' => $bankId,
                'debit_cents' => $transaction->amount_cents > 0 ? $amount : 0,
                'credit_cents' => $transaction->amount_cents < 0 ? $amount : 0,
                'description' => $transaction->description,
                'client_id' => null,
                'supplier_id' => null,
            ],
        ], null);
        $line = $entry->lines->firstWhere('account_id', $bankId);
        $transaction->journal_line_id = $line?->id;
        $transaction->review_status = 'allocated';
        $transaction->save();
    }

    public function ignore(BankTransaction $transaction): void
    {
        $transaction->review_status = 'ignored';
        $transaction->save();
    }

    public function undo(BankTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->payment_id !== null) {
                $payment = Payment::query()->with('allocations')->find($transaction->payment_id);
                if ($payment !== null) {
                    $invoiceIds = $payment->allocations->pluck('invoice_id');
                    JournalEntry::query()->where('source_type', 'payment')->where('source_id', $payment->id)->delete();
                    $payment->allocations()->delete();
                    $payment->delete();
                    foreach (Invoice::query()->whereIn('id', $invoiceIds)->get() as $invoice) {
                        $this->settlement->refresh($invoice);
                    }
                }
            }
            if ($transaction->journal_line_id !== null) {
                $line = JournalLine::query()->find($transaction->journal_line_id);
                if ($line !== null) {
                    JournalEntry::query()->whereKey($line->journal_entry_id)->whereIn('source_type', ['bank_review', 'supplier_payment'])->delete();
                }
            }
            $transaction->payment_id = null;
            $transaction->journal_line_id = null;
            $transaction->review_status = 'pending';
            $transaction->save();
        });
    }

    private function markAllocated(BankTransaction $transaction, Payment $payment): void
    {
        $bankId = (int) $transaction->bankAccount->account_id;
        $line = JournalLine::query()
            ->where('account_id', $bankId)
            ->whereHas('entry', fn ($query) => $query->where('source_type', 'payment')->where('source_id', $payment->id))
            ->first();
        $transaction->payment_id = $payment->id;
        $transaction->journal_line_id = $line?->id;
        $transaction->review_status = 'allocated';
        $transaction->save();
    }
}
