<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PartUsed;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Billing\WorkBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_draft_keeps_billed_links_until_a_line_is_removed(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $this->logTime($admin, $ticket, ['description' => 'On site', 'minutes' => 60]);
        $this->actingAs($admin)->post(route('tickets.parts', $ticket), [
            'description' => 'Cable',
            'quantity' => '2',
            'unit_price' => '80.00',
            'vat_code' => 'standard',
            'billable' => '1',
        ])->assertRedirect();

        $time = TimeEntry::query()->firstOrFail();
        $part = PartUsed::query()->firstOrFail();
        $this->bill($admin, $ticket->client, [
            $this->timeItem($time),
            $this->partItem($part),
        ], 'ticket')->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $time->refresh();
        $part->refresh();
        $this->assertSame($invoice->lines->firstWhere('description', 'On site')->id, $time->invoice_line_id);
        $this->assertSame($invoice->lines->firstWhere('description', 'Cable')->id, $part->invoice_line_id);
        $lineIds = $invoice->lines->pluck('id')->all();

        $this->actingAs($admin)->put(route('invoices.update', $invoice), [
            'client_id' => $ticket->client_id,
            'notes' => 'Rate checked',
            'lines' => $this->linePayload($invoice, 'On site', 'On site, revised'),
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEqualsCanonicalizing($lineIds, $invoice->lines()->pluck('id')->all());
        $this->assertSame($time->invoice_line_id, $time->fresh()->invoice_line_id);
        $this->assertSame($part->invoice_line_id, $part->fresh()->invoice_line_id);
        $this->bill($admin, $ticket->client, [$this->timeItem($time->fresh())], 'ticket')->assertSessionHasErrors('items');
        $this->assertSame(1, Invoice::query()->count());

        $this->actingAs($admin)->put(route('invoices.update', $invoice), [
            'client_id' => $ticket->client_id,
            'lines' => $this->linePayload($invoice->fresh(), 'On site, revised', null),
        ])->assertRedirect();

        $this->assertNull($time->fresh()->invoice_line_id);
        $this->assertSame($part->invoice_line_id, $part->fresh()->invoice_line_id);

        $this->bill($admin, $ticket->client, [$this->timeItem($time->fresh())], 'ticket')->assertRedirect();
        $rebilled = \App\Models\InvoiceLine::query()->findOrFail($time->fresh()->invoice_line_id);
        $this->assertNotSame($invoice->id, $rebilled->invoice_id);
        $this->bill($admin, $ticket->client, [$this->partItem($part->fresh())], 'ticket')->assertSessionHasErrors('items');

        $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertRedirect();
        $this->assertNull($part->fresh()->invoice_line_id);
        $this->assertNotNull($time->fresh()->invoice_line_id);
    }

    public function test_issued_invoices_and_quotes_cannot_be_edited(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), $this->document($client->id, '100.00'))->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1'])->assertRedirect();

        $this->actingAs($admin)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($admin)->put(route('invoices.update', $invoice), $this->document($client->id, '90.00'))->assertForbidden();
        $this->actingAs($admin)->get(route('invoices.show', $invoice))
            ->assertSee('This invoice is locked')
            ->assertSee('Credit note');
        $this->actingAs($admin)->post(route('credit-notes.store', $invoice), [
            'notes' => 'Correction',
            'lines' => [[
                'description' => 'Correction',
                'quantity' => '1',
                'unit_price' => '20.00',
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ])->assertRedirect();
        $this->assertSame(1, $invoice->fresh()->creditNotes()->count());

        $this->actingAs($admin)->post(route('quotes.store'), $this->document($client->id, '80.00'))->assertRedirect();
        $quote = Quote::query()->firstOrFail();
        $this->actingAs($admin)->post(route('quotes.issue', $quote), ['confirm' => '1'])->assertRedirect();
        $this->actingAs($admin)->put(route('quotes.update', $quote), $this->document($client->id, '70.00'))->assertForbidden();
    }

    public function test_invoice_lines_can_be_negative(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), $this->document($client->id, '-50.00', 'Goodwill discount'))->assertRedirect();

        $line = Invoice::query()->firstOrFail()->lines->first();
        $this->assertSame(-5000, $line->net_cents);
        $this->assertSame(-750, $line->vat_cents);
        $this->assertSame(-5750, $line->total_cents);
        $this->actingAs($admin)->get(route('invoices.show', $line->invoice_id))->assertSee('-R 50.00');
    }

    public function test_billing_wizard_marks_selected_work_and_refuses_a_second_bill(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $first = Ticket::factory()->create(['client_id' => $client->id, 'subject' => 'Printer offline']);
        $second = Ticket::factory()->create(['client_id' => $client->id, 'subject' => 'Gate motor']);
        $this->logTime($admin, $first, ['description' => 'On site', 'minutes' => 40]);
        $this->logTime($admin, $first, ['description' => 'Quick check', 'minutes' => 10]);
        $this->logTime($admin, $first, ['description' => 'Night work', 'minutes' => 60, 'after_hours' => '1']);
        $this->logTime($admin, $first, ['description' => 'Site call-out', 'kind' => 'call_out', 'minutes' => 0]);
        $this->logTime($admin, $second, ['description' => 'Drive to Athlone', 'kind' => 'travel', 'minutes' => 0, 'quantity' => '12.5']);
        $this->actingAs($admin)->post(route('tickets.parts', $first), [
            'description' => 'Cable',
            'quantity' => '2',
            'unit_price' => '80.00',
            'vat_code' => 'standard',
            'billable' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('tickets.bill', $first))
            ->assertOk()
            ->assertSee('On site')
            ->assertSee('0.750')
            ->assertSee('0.500')
            ->assertSee('650.00')
            ->assertSee('975.00')
            ->assertSee('450.00')
            ->assertDontSee('Drive to Athlone');
        $this->actingAs($admin)->get(route('clients.bill', $client))
            ->assertOk()
            ->assertSee('Drive to Athlone')
            ->assertSee('12.500')
            ->assertSee('6.50');

        $night = TimeEntry::query()->where('description', 'Night work')->firstOrFail();
        $items = [];
        foreach (TimeEntry::query()->where('id', '!=', $night->id)->get() as $entry) {
            $items[] = $this->timeItem($entry);
        }
        $items[] = $this->partItem(PartUsed::query()->firstOrFail());
        $this->bill($admin, $client, $items, 'type')->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $labour = $invoice->lines->firstWhere('description', 'Labour');
        $this->assertNotNull($labour);
        $this->assertSame('1.250', (string) $labour->quantity);
        $this->assertSame(65000, $labour->unit_price_cents);
        $this->assertSame(4, $invoice->lines->count());

        $billed = TimeEntry::query()->where('id', '!=', $night->id)->get();
        $this->assertTrue($billed->every(fn (TimeEntry $entry) => $entry->invoice_line_id !== null));
        $this->assertSame(
            $labour->id,
            TimeEntry::query()->where('description', 'On site')->value('invoice_line_id'),
        );
        $this->assertSame(
            $labour->id,
            TimeEntry::query()->where('description', 'Quick check')->value('invoice_line_id'),
        );
        $this->assertNull($night->fresh()->invoice_line_id);
        $this->assertNotNull(PartUsed::query()->value('invoice_line_id'));
        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertSee('Time summary')->assertSee($first->reference());

        $this->bill($admin, $client, [$this->timeItem(TimeEntry::query()->where('description', 'On site')->firstOrFail())], 'ticket')
            ->assertSessionHasErrors('items');
        $this->assertSame(1, Invoice::query()->count());
        $this->assertNull($night->fresh()->invoice_line_id);
    }

    public function test_billing_defaults_follow_settings_and_client_overrides(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $this->actingAs($admin)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('value="650.00"', false)
            ->assertSee('value="1.5"', false)
            ->assertSee('value="450.00"', false)
            ->assertSee('value="6.50"', false)
            ->assertSee('value="15"', false);

        $this->logTime($admin, $ticket, ['description' => 'Standard', 'minutes' => 40]);
        $this->assertSame(65000, TimeEntry::query()->where('description', 'Standard')->value('hourly_rate_cents'));

        $this->actingAs($admin)->put(route('settings.update'), $this->settings(['travel_rate' => '7.00']))->assertRedirect();
        $this->actingAs($admin)->put(route('clients.update', $client), [
            'name' => $client->name,
            'payment_terms_days' => 30,
            'country' => 'South Africa',
            'is_active' => '1',
            'labour_rate' => '800.00',
        ])->assertRedirect();
        $this->assertSame(80000, $client->fresh()->labour_rate_cents);

        $this->logTime($admin, $ticket, ['description' => 'Agreed rate', 'minutes' => 30]);
        $this->logTime($admin, $ticket, ['description' => 'Extra trip', 'kind' => 'travel', 'minutes' => 0, 'quantity' => '4']);
        $this->assertSame(80000, TimeEntry::query()->where('description', 'Agreed rate')->value('hourly_rate_cents'));
        $this->assertSame(700, TimeEntry::query()->where('description', 'Extra trip')->value('hourly_rate_cents'));
        $this->actingAs($admin)->get(route('tickets.bill', $ticket))
            ->assertSee('800.00')
            ->assertSee('7.00');
    }

    private function logTime(User $user, Ticket $ticket, array $extra): void
    {
        $this->actingAs($user)->post(route('tickets.time', $ticket), array_merge([
            'work_date' => today()->toDateString(),
            'kind' => 'labour',
            'minutes' => 60,
            'description' => 'On site',
            'vat_code' => 'standard',
            'billable' => '1',
        ], $extra))->assertRedirect();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function bill(User $user, Client $client, array $items, string $group): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('clients.bill.store', $client), [
            'group' => $group,
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function timeItem(TimeEntry $entry): array
    {
        $suggestion = app(WorkBilling::class)->suggestion($entry);

        return [
            'include' => '1',
            'type' => 'time',
            'id' => $entry->id,
            'description' => $suggestion['description'],
            'quantity' => $suggestion['quantity'],
            'unit_price' => $suggestion['unit_price'],
            'includes_vat' => '0',
            'vat_code' => $suggestion['vat_code'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partItem(PartUsed $part): array
    {
        $suggestion = app(WorkBilling::class)->partSuggestion($part);

        return [
            'include' => '1',
            'type' => 'part',
            'id' => $part->id,
            'product_id' => $part->product_id,
            'description' => $suggestion['description'],
            'quantity' => $suggestion['quantity'],
            'unit_price' => $suggestion['unit_price'],
            'includes_vat' => '0',
            'vat_code' => $suggestion['vat_code'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linePayload(Invoice $invoice, string $from, ?string $to): array
    {
        return $invoice->lines->map(function ($line) use ($from, $to) {
            if ($to === null && $line->description === $from) {
                return null;
            }

            return [
                'id' => $line->id,
                'description' => $line->description === $from && $to !== null ? $to : $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => $line->unitPriceRands(),
                'includes_vat' => $line->price_includes_vat ? '1' : '0',
                'vat_code' => $line->vat_code->value,
            ];
        })->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function document(int $clientId, string $price, string $description = 'IT support'): array
    {
        return [
            'client_id' => $clientId,
            'lines' => [[
                'description' => $description,
                'quantity' => '1',
                'unit_price' => $price,
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'country' => 'South Africa',
            'email' => 'info@originalconcepts.co.za',
            'default_labour_rate' => '650.00',
            'after_hours_multiplier' => '1.5',
            'call_out_fee' => '450.00',
            'minimum_billable_minutes' => '30',
            'rounding_minutes' => '15',
            'travel_rate' => '6.50',
            'standard_vat_percent' => '15',
            'show_prices_excl_vat' => '1',
            'invoice_time_layout' => 'summary',
        ], $overrides);
    }
}
