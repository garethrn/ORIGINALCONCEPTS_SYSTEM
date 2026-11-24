<?php

namespace Tests\Feature;

use App\Contracts\Ai\ChatClient;
use App\Contracts\Ai\ChatCompletion;
use App\Contracts\Ai\ToolCall;
use App\Enums\Role;
use App\Mail\DocumentMail;
use App\Models\AiDraft;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['support.ai.enabled' => true, 'support.ai.driver' => 'fake']);
    }

    public function test_assistant_is_off_without_a_key(): void
    {
        config(['support.ai.enabled' => false, 'support.ai.driver' => 'openai', 'support.ai.key' => null]);
        $this->actingAs($this->admin())->get(route('assistant.index'))
            ->assertOk()
            ->assertSee('switched off');
        $this->actingAs($this->admin())->post(route('assistant.store'), ['message' => 'Invoice Acme'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_invoice_tool_creates_a_preview_and_issues_only_after_confirm(): void
    {
        Mail::fake();
        $this->companyVat();
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Acme', 'trading_name' => null, 'email' => 'accounts@acme.test']);
        Product::factory()->create(['name' => '4MP IP camera', 'sku' => 'HW-CAM-4MP', 'unit_price_cents' => 145000]);
        $this->script(new ToolCall('call_1', 'create_invoice_draft', [
            'customer' => 'Acme',
            'lines' => [
                ['description' => 'IT support', 'quantity' => '3', 'unit_price' => '650.00', 'includes_vat' => false, 'vat_code' => 'standard'],
                ['description' => '4MP IP camera', 'product' => '4MP IP camera', 'quantity' => '1', 'unit_price' => '', 'includes_vat' => false, 'vat_code' => 'standard'],
            ],
        ]));

        $this->actingAs($admin)->post(route('assistant.store'), [
            'message' => 'Invoice Acme for 3 hours IT support at R650 and a 4MP IP camera',
        ])->assertRedirect();

        $this->assertSame(0, Invoice::query()->count());
        $draft = AiDraft::query()->first();
        $this->assertNotNull($draft);
        $this->assertSame('pending', $draft->status);

        $this->actingAs($admin)->get(route('assistant.index', ['draft' => $draft->id]))
            ->assertOk()
            ->assertSee('nothing has been issued')
            ->assertSee('IT support')
            ->assertSee('4MP IP camera');

        $this->actingAs($admin)->post(route('assistant.confirm', $draft), [
            'decision' => 'save',
            'confirm' => '1',
        ])->assertRedirect();

        $invoice = Invoice::query()->first();
        $this->assertNull($invoice->number);
        $this->assertSame(195000 + 145000, $invoice->subtotal_cents);

        $this->script(new ToolCall('call_2', 'create_invoice_draft', [
            'customer' => 'Acme',
            'lines' => [
                ['description' => 'IT support', 'quantity' => '1', 'unit_price' => '100.00', 'includes_vat' => false, 'vat_code' => 'standard'],
            ],
        ]));
        $this->actingAs($admin)->post(route('assistant.store'), ['message' => 'Invoice Acme for 1 hour']);
        $second = AiDraft::query()->where('status', 'pending')->first();
        $this->actingAs($admin)->post(route('assistant.confirm', $second), [
            'decision' => 'issue_and_email',
            'confirm' => '1',
        ])->assertRedirect();
        $issued = Invoice::query()->whereNotNull('number')->first();
        $this->assertSame('INV-00001', $issued->number);
        Mail::assertQueued(DocumentMail::class);
    }

    public function test_ambiguous_customer_does_not_create_a_draft(): void
    {
        Client::factory()->create(['name' => 'Acme Printers', 'trading_name' => null]);
        Client::factory()->create(['name' => 'Acme Holdings', 'trading_name' => null]);
        $this->script(new ToolCall('call_1', 'create_invoice_draft', [
            'customer' => 'Acme',
            'lines' => [['description' => 'IT support', 'quantity' => '1', 'unit_price' => '100.00', 'includes_vat' => false, 'vat_code' => 'standard']],
        ]));

        $this->actingAs($this->admin())->post(route('assistant.store'), ['message' => 'Invoice Acme'])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame(0, AiDraft::query()->count());
        $this->actingAs($this->admin())->get(route('assistant.index'))->assertSee('Which customer');
    }

    public function test_technician_cannot_draft_an_invoice_and_bad_input_is_rejected(): void
    {
        Client::factory()->create(['name' => 'Acme', 'trading_name' => null]);
        $this->script(new ToolCall('call_1', 'create_invoice_draft', [
            'customer' => 'Acme',
            'lines' => [['description' => 'IT support', 'quantity' => '0', 'unit_price' => '100.00', 'includes_vat' => false, 'vat_code' => 'standard']],
        ]));
        $this->actingAs($this->userWithRole(Role::Technician))->post(route('assistant.store'), ['message' => 'Invoice Acme'])
            ->assertSessionHas('status');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, AiDraft::query()->count());

        $this->script(new ToolCall('call_2', 'create_invoice_draft', [
            'customer' => 'Acme',
            'lines' => [['description' => 'IT support', 'quantity' => '0', 'unit_price' => '100.00', 'includes_vat' => false, 'vat_code' => 'standard']],
        ]));
        $this->actingAs($this->admin())->post(route('assistant.store'), ['message' => '<script>alert(1)</script>'])->assertRedirect();
        $this->actingAs($this->admin())->get(route('assistant.index'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_openai_compatible_client_sends_tools_without_logging_the_key(): void
    {
        config([
            'support.ai.driver' => 'openai',
            'support.ai.key' => 'sk-test-secret',
            'support.ai.base_url' => 'https://ai.example.test/v1',
            'support.ai.model' => 'test-model',
            'support.ai.enabled' => true,
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Need a customer.']]],
            ]),
        ]);
        $this->app->forgetInstance(ChatClient::class);

        $this->actingAs($this->admin())->post(route('assistant.store'), ['message' => 'Hello']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://ai.example.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-secret')
                && ($request['tools'] ?? null) !== null
                && ($request['model'] ?? null) === 'test-model';
        });
    }

    private function script(ToolCall $call): void
    {
        $this->app->instance(ChatClient::class, new class($call) implements ChatClient
        {
            public function __construct(private ToolCall $call) {}

            public function complete(array $messages, array $tools): ChatCompletion
            {
                return new ChatCompletion(null, [$this->call], [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => $this->call->id,
                        'type' => 'function',
                        'function' => ['name' => $this->call->name, 'arguments' => '{}'],
                    ]],
                ]);
            }
        });
    }
}
