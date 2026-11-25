<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_payments_credit_notes_bills_and_expenses_post_balanced_journals(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'lines' => [[
                'description' => 'IT support',
                'quantity' => '1',
                'unit_price' => '100.00',
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ]);
        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1']);

        $entry = JournalEntry::query()->where('source_type', 'invoice')->firstOrFail();
        $this->assertSame((int) $entry->lines()->sum('debit_cents'), (int) $entry->lines()->sum('credit_cents'));
        $this->assertSame(11500, (int) $entry->lines()->where('account_id', Account::forRole('receivable')->id)->sum('debit_cents'));
        $this->assertSame(1500, (int) $entry->lines()->where('account_id', Account::forRole('vat_output')->id)->sum('credit_cents'));

        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '115.00',
            'paid_on' => today()->toDateString(),
            'method' => 'eft',
            'reference' => 'EFT1',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '115.00']],
        ])->assertRedirect();
        $payment = JournalEntry::query()->where('source_type', 'payment')->firstOrFail();
        $this->assertSame(11500, (int) $payment->lines()->where('account_id', Account::forRole('bank')->id)->sum('debit_cents'));

        $this->actingAs($admin)->post(route('journals.store'), [
            'entry_date' => today()->toDateString(),
            'memo' => 'Owner draw',
            'lines' => [
                ['account_id' => Account::query()->where('code', '3200')->value('id'), 'debit' => '10.00', 'credit' => ''],
                ['account_id' => Account::forRole('bank')->id, 'debit' => '', 'credit' => '10.00'],
            ],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('journals.store'), [
            'entry_date' => today()->toDateString(),
            'memo' => 'Unbalanced',
            'lines' => [
                ['account_id' => Account::forRole('bank')->id, 'debit' => '5.00', 'credit' => ''],
                ['account_id' => Account::forRole('sales')->id, 'debit' => '', 'credit' => '4.00'],
            ],
        ])->assertSessionHasErrors('lines');

        $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'Cable Co',
            'payment_terms_days' => 30,
        ])->assertRedirect();
        $supplier = Supplier::query()->firstOrFail();
        $this->actingAs($admin)->post(route('bills.store', $supplier), [
            'lines' => [[
                'description' => 'Cable',
                'quantity' => '1',
                'unit_price' => '80.00',
                'vat_code' => 'standard',
            ]],
        ])->assertRedirect();
        $bill = $supplier->bills()->firstOrFail();
        $this->actingAs($admin)->post(route('bills.issue', $bill))->assertRedirect();
        $billEntry = JournalEntry::query()->where('source_type', 'bill')->firstOrFail();
        $this->assertSame(9200, (int) $billEntry->lines()->where('account_id', Account::forRole('payable')->id)->sum('credit_cents'));

        $this->actingAs($admin)->post(route('expenses.store'), [
            'payee' => 'Parking',
            'account_id' => Account::forRole('expense')->id,
            'spent_on' => today()->toDateString(),
            'amount' => '20.00',
            'vat_code' => 'standard',
        ])->assertRedirect();
        $this->assertNotNull(JournalEntry::query()->where('source_type', 'expense')->first());
    }

    public function test_bank_csv_import_rules_and_reconciliation(): void
    {
        $admin = $this->admin();
        $bank = BankAccount::query()->firstOrFail();
        $this->actingAs($admin)->post(route('banking.rules.store', $bank), [
            'contains' => 'service fee',
            'account_id' => Account::query()->where('code', '6100')->value('id'),
        ])->assertRedirect();

        $csv = "date,description,amount\n".today()->toDateString().",Customer deposit,100.00\n".today()->toDateString().",Monthly service fee,-15.00\n";
        $this->actingAs($admin)->post(route('banking.import', $bank), [
            'format' => 'csv',
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ])->assertRedirect();

        $this->assertSame(2, $bank->transactions()->count());
        $fee = $bank->transactions()->where('description', 'Monthly service fee')->firstOrFail();
        $this->assertNotNull($fee->journal_line_id);
        $deposit = $bank->transactions()->where('description', 'Customer deposit')->firstOrFail();
        $this->assertNull($deposit->journal_line_id);

        $this->actingAs($admin)->post(route('banking.reconcile', $bank), [
            'statement_date' => today()->toDateString(),
            'statement_balance' => '85.00',
            'transactions' => [$fee->id, $deposit->id],
        ])->assertRedirect();
        $this->assertNotNull($fee->fresh()->reconciliation_id);
        $this->assertNotNull($deposit->fresh()->reconciliation_id);
    }
}
