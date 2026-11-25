<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\VatCode;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_received_purchase_order_increases_stock_and_an_invoice_reduces_it(): void
    {
        $this->companyVat();
        $admin = $this->admin();
        $product = Product::query()->create([
            'sku' => 'CABLE',
            'name' => 'Network cable',
            'type' => ProductType::Stock,
            'unit' => 'each',
            'unit_price_cents' => 8000,
            'price_includes_vat' => false,
            'vat_code' => VatCode::Standard,
            'is_active' => true,
            'track_stock' => true,
            'quantity_on_hand' => 0,
        ]);
        $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'Cable Co',
            'payment_terms_days' => 30,
        ])->assertRedirect();
        $supplier = Supplier::query()->firstOrFail();
        $this->actingAs($admin)->post(route('purchase-orders.store', $supplier), [
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Network cable',
                'quantity' => '5',
                'unit_price' => '80.00',
                'vat_code' => 'standard',
            ]],
        ])->assertRedirect();
        $order = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($admin)->post(route('purchase-orders.issue', $order))->assertRedirect();
        $this->assertSame('PO-00001', $order->fresh()->number);
        $this->actingAs($admin)->post(route('purchase-orders.receive', $order))->assertRedirect();
        $this->assertSame('5.000', (string) $product->fresh()->quantity_on_hand);
        $this->assertNotNull($order->fresh()->bill_id);

        $client = \App\Models\Client::factory()->create();
        $this->actingAs($admin)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Network cable',
                'quantity' => '2',
                'unit_price' => '80.00',
                'includes_vat' => '0',
                'vat_code' => 'standard',
            ]],
        ])->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.issue', $invoice), ['confirm' => '1'])->assertRedirect();
        $this->assertSame('3.000', (string) $product->fresh()->quantity_on_hand);
    }
}
