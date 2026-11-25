<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Accounting\Vat201;
use App\Services\Billing\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat201_profit_and_loss_and_aged_receivables(): void
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

        $fields = collect(app(Vat201::class)->fields(today()->startOfMonth(), today()))->keyBy('field');
        $this->assertSame(11500, $fields['1']['cents']);
        $this->assertSame(1500, $fields['4']['cents']);
        $this->assertSame(1500, $fields['13']['cents']);
        $this->assertSame(1500, $fields['20']['cents']);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'payee' => 'Stationery',
            'account_id' => Account::forRole('expense')->id,
            'spent_on' => today()->toDateString(),
            'amount' => '80.00',
            'vat_code' => 'standard',
        ])->assertRedirect();
        $fields = collect(app(Vat201::class)->fields(today()->startOfMonth(), today()))->keyBy('field');
        $this->assertSame(1200, $fields['15']['cents']);
        $this->assertSame(300, $fields['20']['cents']);

        $this->actingAs($admin)->get(route('reports.show', [
            'report' => 'profit-and-loss',
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'compare' => '1',
        ]))->assertOk()->assertSee('Net profit')->assertSee('R 20.00');

        $this->actingAs($admin)->get(route('reports.show', [
            'report' => 'aged-receivables',
            'to' => today()->toDateString(),
        ]))->assertOk()->assertSee('INV-00001')->assertSee('R 115.00');

        $csv = $this->actingAs($admin)->get(route('reports.show', [
            'report' => 'vat201',
            'export' => 'csv',
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]));
        $this->assertStringContainsString('VAT payable', $csv->streamedContent());

        $this->actingAs($admin)->get(route('reports.drill', [
            'account' => Account::forRole('sales')->id,
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]))->assertOk()->assertSee('Invoice INV-00001');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Income this month')->assertSee('R 100.00');
    }

    public function test_cash_basis_moves_output_vat_when_the_invoice_is_paid(): void
    {
        $this->companyVat();
        app(CompanyProfile::class)->update(['vat_basis' => 'cash']);
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
        $before = collect(app(Vat201::class)->fields(today()->startOfMonth(), today()))->keyBy('field');
        $this->assertSame(0, $before['4']['cents']);

        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '115.00',
            'paid_on' => today()->toDateString(),
            'method' => 'eft',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '115.00']],
        ])->assertRedirect();
        $after = collect(app(Vat201::class)->fields(today()->startOfMonth(), today()))->keyBy('field');
        $this->assertSame(1500, $after['4']['cents']);
        $this->assertSame(11500, $after['1']['cents']);
    }
}
