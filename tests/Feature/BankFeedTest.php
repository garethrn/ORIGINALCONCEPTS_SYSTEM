<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankEmail;
use App\Models\BankMailbox;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Banking\BankFeedImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BankFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_fnb_and_standard_bank_alerts_allocate_and_do_not_import_twice(): void
    {
        Mail::fake();
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Acme Printing']);
        $this->actingAs($admin)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'lines' => [[
                'description' => 'IT support',
                'quantity' => '1',
                'unit_price' => '1000.00',
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ]);
        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1']);
        $bank = BankAccount::query()->firstOrFail();
        $bank->account_last4 = '1234';
        $bank->save();
        $mailbox = BankMailbox::query()->create([
            'name' => 'Alerts',
            'allowed_senders' => "owner@originalconcepts.co.za\n",
            'auto_allocate' => true,
            'confidence_threshold' => 90,
            'active' => true,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
        ]);

        $fnb = $this->email(
            'fnb@fnb.co.za',
            'FNB transaction',
            '<fnb-100@fnb.co.za>',
            'FNB :-) R1,150.00 received from ACME PRINTING. Ref.INV-00001. Avail R5,000.00. 25/11/2026 08:01. Acc.6212****1234',
        );
        $result = app(BankFeedImporter::class)->ingest($fnb, $mailbox);
        $this->assertFalse($result['duplicate']);
        $this->assertSame(1, $result['allocated']);
        $transaction = BankTransaction::query()->firstOrFail();
        $this->assertSame(115000, $transaction->amount_cents);
        $this->assertSame('allocated', $transaction->review_status);
        $this->assertSame('fnb', $transaction->parser);
        $this->assertSame(0, $invoice->fresh()->balanceCents());
        $this->assertSame(1, Payment::query()->count());

        $again = app(BankFeedImporter::class)->ingest($fnb, $mailbox);
        $this->assertTrue($again['duplicate']);
        $this->assertSame(1, BankEmail::query()->count());
        $this->assertSame(1, BankTransaction::query()->count());

        $this->actingAs($admin)->post(route('banking.review.undo', $transaction))->assertRedirect();
        $this->assertSame('pending', $transaction->fresh()->review_status);
        $this->assertSame(115000, $invoice->fresh()->balanceCents());

        $standard = $this->email(
            'notify@standardbank.co.za',
            'Standard Bank payment notification',
            '<std-200@standardbank.co.za>',
            "Standard Bank notification\nAccount: ****1234\nDate: 25 Nov 2026\nCredit: R 1 150.00\nFrom: ACME PRINTING\nReference: INV-00001\n",
        );
        $second = app(BankFeedImporter::class)->ingest($standard, $mailbox);
        $this->assertSame(1, $second['allocated']);
        $this->assertSame('standard_bank', BankTransaction::query()->where('parser', 'standard_bank')->value('parser'));
        $this->assertSame(0, $invoice->fresh()->balanceCents());
    }

    public function test_a_low_confidence_payment_stays_for_review_and_a_foreign_sender_is_ignored(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Acme Printing']);
        foreach (['400.00', '400.00'] as $price) {
            $this->actingAs($admin)->post(route('invoices.store'), [
                'client_id' => $client->id,
                'lines' => [[
                    'description' => 'Support',
                    'quantity' => '1',
                    'unit_price' => $price,
                    'includes_vat' => '0',
                    'vat_code' => 'standard',
                ]],
            ]);
        }
        foreach (Invoice::query()->get() as $invoice) {
            $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1']);
        }
        $bank = BankAccount::query()->firstOrFail();
        $bank->account_last4 = '5678';
        $bank->save();
        $mailbox = BankMailbox::query()->create([
            'auto_allocate' => true,
            'confidence_threshold' => 90,
            'active' => true,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
        ]);
        $raw = $this->email(
            'alerts@fnb.co.za',
            'FNB',
            '<fnb-ambiguous@fnb.co.za>',
            'FNB :-) R460.00 received from SOMEONE ELSE. Ref.CARD. 25/11/2026. Acc ****5678',
        );
        $result = app(BankFeedImporter::class)->ingest($raw, $mailbox);
        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, $result['allocated']);
        $this->assertSame(0, Payment::query()->count());

        $ignored = $this->email('stranger@example.com', 'Money', '<stranger@example.com>', 'FNB :-) R10.00 received. Acc ****5678. 25/11/2026.');
        app(BankFeedImporter::class)->ingest($ignored, $mailbox);
        $this->assertSame('ignored', BankEmail::query()->where('from_address', 'stranger@example.com')->value('status'));
        $this->assertSame(1, BankTransaction::query()->count());
        $this->assertTrue(Account::forRole('bank')->exists());
    }

    private function email(string $from, string $subject, string $id, string $body): string
    {
        return "From: {$from}\nSubject: {$subject}\nMessage-ID: {$id}\nDate: Wed, 25 Nov 2026 08:01:00 +0200\nContent-Type: text/plain\n\n{$body}\n";
    }
}
