<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\Billing\DocumentNumberer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_math_numbering_lock_and_credit_note(): void
    {
        Mail::fake();
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Acme (Pty) Ltd', 'vat_number' => '4987654321', 'payment_terms_days' => 14]);

        $this->actingAs($admin)->post(route('invoices.store'), $this->payload($client->id, '3', '650.00'))->assertRedirect();
        $first = Invoice::query()->first();
        $this->assertNull($first->number);
        $this->assertSame(195000, $first->subtotal_cents);
        $this->assertSame(29250, $first->vat_cents);
        $this->assertSame(224250, $first->total_cents);

        $this->actingAs($admin)->post(route('invoices.issue', $first), ['confirm' => '1'])->assertRedirect();
        $first->refresh();
        $this->assertSame('INV-00001', $first->number);
        $this->assertSame(InvoiceStatus::Issued, $first->status);
        $this->assertTrue($first->due_date->equalTo(today()->addDays(14)));

        $this->actingAs($admin)->put(route('invoices.update', $first), $this->payload($client->id, '1', '10.00'))->assertForbidden();

        $this->actingAs($admin)->post(route('invoices.store'), $this->payload($client->id, '1', '1150.00', true))->assertRedirect();
        $inclusive = Invoice::query()->whereNull('number')->first();
        $this->assertSame(100000, $inclusive->subtotal_cents);
        $this->assertSame(15000, $inclusive->vat_cents);

        $this->actingAs($admin)->post(route('invoices.store'), $this->payload($client->id, '1', '10.00'))->assertRedirect();
        $third = Invoice::query()->whereNull('number')->latest('id')->first();
        $this->actingAs($admin)->post(route('invoices.issue', $third), ['confirm' => '1']);
        $this->assertSame('INV-00002', $third->fresh()->number);

        $this->actingAs($admin)->get(route('invoices.show', $first))
            ->assertOk()
            ->assertSee('Tax Invoice')
            ->assertSee('Original Concepts (Pty) Ltd')
            ->assertSee('4123456789')
            ->assertSee('Acme (Pty) Ltd')
            ->assertSee('4987654321')
            ->assertSee('INV-00001');

        $this->actingAs($admin)->get(route('invoices.pdf', $first))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $this->actingAs($admin)->get(route('invoices.pdf', $first))->getContent());

        $this->actingAs($admin)->post(route('credit-notes.store', $first), [
            'notes' => 'Correction',
            'lines' => [[
                'description' => 'IT support',
                'quantity' => '3',
                'unit_price' => '650.00',
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ])->assertRedirect();
        $credit = $first->creditNotes()->first();
        $this->actingAs($admin)->post(route('credit-notes.issue', $credit), ['confirm' => '1'])->assertRedirect();
        $credit->refresh();
        $this->assertSame('CN-00001', $credit->number);
        $this->actingAs($admin)->get(route('credit-notes.show', $credit))
            ->assertSee('Credit Note')
            ->assertSee('INV-00001');
        $this->assertSame(InvoiceStatus::Credited, $first->fresh()->status);
    }

    public function test_a_failed_issue_does_not_consume_a_number(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            DB::transaction(function () {
                app(DocumentNumberer::class)->next(DocumentType::Invoice);
                throw new RuntimeException('rollback');
            });
        } finally {
            $this->assertSame('INV-00001', app(DocumentNumberer::class)->next(DocumentType::Invoice));
        }
    }

    public function test_quote_converts_to_a_draft_invoice_without_a_number(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('quotes.store'), $this->payload($client->id, '2', '100.00'))->assertRedirect();
        $quote = Quote::query()->first();
        $this->actingAs($admin)->post(route('quotes.issue', $quote), ['confirm' => '1']);
        $this->assertSame('QUO-00001', $quote->fresh()->number);
        $this->actingAs($admin)->post(route('quotes.convert', $quote))->assertRedirect();
        $invoice = Invoice::query()->first();
        $this->assertNull($invoice->number);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($quote->fresh()->total_cents, $invoice->total_cents);
        $this->assertSame($quote->id, $invoice->quote_id);
    }

    public function test_part_payment_and_over_allocation(): void
    {
        $this->companyVat();
        $finance = $this->userWithRole(Role::Finance);
        $manager = $this->userWithRole(Role::Manager);
        $client = Client::factory()->create();
        $this->actingAs($finance)->post(route('invoices.store'), $this->payload($client->id, '1', '100.00'));
        $invoice = Invoice::query()->first();
        $this->actingAs($finance)->post(route('invoices.issue', $invoice), ['confirm' => '1']);

        $this->actingAs($manager)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '50.00',
            'paid_on' => today()->toDateString(),
            'method' => 'eft',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '50.00']],
        ])->assertForbidden();

        $this->actingAs($finance)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '40.00',
            'paid_on' => today()->toDateString(),
            'method' => 'eft',
            'reference' => 'EFT1',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '40.00']],
        ])->assertRedirect();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(7500, $invoice->fresh()->balanceCents());

        $this->actingAs($finance)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '100.00',
            'paid_on' => today()->toDateString(),
            'method' => 'cash',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
        ])->assertSessionHasErrors('allocations');
    }

    public function test_issuing_is_blocked_until_the_company_vat_number_is_set(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), $this->payload($client->id, '1', '10.00'));
        $invoice = Invoice::query()->first();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1'])->assertSessionHasErrors('vat');
        $this->assertNull($invoice->fresh()->number);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $clientId, string $quantity, string $price, bool $includesVat = false): array
    {
        return [
            'client_id' => $clientId,
            'notes' => null,
            'lines' => [[
                'description' => 'IT support',
                'quantity' => $quantity,
                'unit_price' => $price,
                'includes_vat' => $includesVat ? '1' : '0',
                'vat_code' => 'standard',
            ]],
        ];
    }
}
