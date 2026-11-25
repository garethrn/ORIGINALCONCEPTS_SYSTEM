<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_picks_one_quote_option_and_optional_lines(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('quotes.store'), [
            'client_id' => $client->id,
            'options' => [
                ['name' => 'Repair'],
                ['name' => 'Replace'],
            ],
            'lines' => [
                $this->line('Call-out', '450.00', 'included'),
                $this->line('Repair labour', '650.00', 'option-0'),
                $this->line('New printer', '2000.00', 'option-1'),
                $this->line('Extended cover', '300.00', 'optional'),
            ],
        ])->assertRedirect();

        $quote = Quote::query()->firstOrFail();
        $this->assertCount(2, $quote->options);
        $this->actingAs($admin)->post(route('quotes.issue', $quote), ['confirm' => '1'])->assertRedirect();
        $this->actingAs($admin)->post(route('quotes.convert', $quote))->assertSessionHasErrors('quote');
        $this->assertSame(0, Invoice::query()->count());

        $replace = $quote->options()->where('name', 'Replace')->firstOrFail();
        $cover = $quote->lines()->where('description', 'Extended cover')->firstOrFail();
        $this->actingAs($admin)->post(route('quotes.choose', $quote), [
            'option_id' => $replace->id,
            'optional_lines' => [$cover->id],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('quotes.convert', $quote))->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();
        $descriptions = $invoice->lines->pluck('description')->all();
        $this->assertEqualsCanonicalizing(['Call-out', 'New printer', 'Extended cover'], $descriptions);
        $this->assertNotContains('Repair labour', $descriptions);
        $this->assertSame(316250, $invoice->total_cents);
        $this->assertSame($quote->id, $invoice->quote_id);
    }

    public function test_recurring_schedule_creates_a_draft_once_and_can_auto_issue(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('recurring.store'), [
            'client_id' => $client->id,
            'name' => 'Monthly support',
            'frequency' => 'monthly',
            'next_on' => today()->toDateString(),
            'auto_issue' => '0',
            'active' => '1',
            'lines' => [$this->line('Support retainer', '100.00', 'included')],
        ])->assertRedirect();

        $this->artisan('billing:recurring')->assertSuccessful();
        $draft = Invoice::query()->firstOrFail();
        $this->assertSame(InvoiceStatus::Draft, $draft->status);
        $this->assertNull($draft->number);
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), RecurringSchedule::query()->firstOrFail()->next_on->toDateString());

        $this->artisan('billing:recurring')->assertSuccessful();
        $this->assertSame(1, Invoice::query()->count());

        $this->actingAs($admin)->post(route('recurring.store'), [
            'client_id' => $client->id,
            'name' => 'Hosted backup',
            'frequency' => 'monthly',
            'next_on' => today()->toDateString(),
            'auto_issue' => '1',
            'active' => '1',
            'lines' => [$this->line('Backup', '50.00', 'included')],
        ])->assertRedirect();
        $this->artisan('billing:recurring')->assertSuccessful();
        $issued = Invoice::query()->where('notes', 'Recurring: Hosted backup')->firstOrFail();
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertNotNull($issued->number);
    }

    public function test_statement_shows_invoices_payments_and_the_closing_balance(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'lines' => [$this->line('IT support', '100.00', 'included')],
        ])->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1'])->assertRedirect();
        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '40.00',
            'paid_on' => today()->toDateString(),
            'method' => 'eft',
            'reference' => 'EFT9',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '40.00']],
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('clients.statement', [
            'client' => $client,
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('INV-00001')
            ->assertSee('EFT9')
            ->assertSee('R 75.00');

        $csv = $this->actingAs($admin)->get(route('clients.statement.csv', $client));
        $csv->assertOk();
        $this->assertStringContainsString('75.00', $csv->streamedContent());
    }

    /**
     * @return array<string, string>
     */
    private function line(string $description, string $price, string $placement): array
    {
        return [
            'description' => $description,
            'quantity' => '1',
            'unit_price' => $price,
            'includes_vat' => '0',
            'vat_code' => 'standard',
            'placement' => $placement,
        ];
    }
}
