<?php

namespace Tests\Feature;

use App\Contracts\Payments\OnlinePaymentGateway;
use App\Contracts\Payments\RecurringBillingGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndPwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_out_and_there_is_no_default_user(): void
    {
        $this->assertSame(0, User::query()->count());
        User::factory()->create(['email' => 'person@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'person@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'person@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_create_admin_reads_the_environment_and_rejects_a_weak_password(): void
    {
        putenv('ADMIN_NAME=Gareth Naidoo');
        putenv('ADMIN_EMAIL=gareth@example.com');
        putenv('ADMIN_PASSWORD=short');
        $_ENV['ADMIN_NAME'] = 'Gareth Naidoo';
        $_ENV['ADMIN_EMAIL'] = 'gareth@example.com';
        $_ENV['ADMIN_PASSWORD'] = 'short';

        $this->artisan('app:create-admin', ['--no-interaction' => true])->assertFailed();
        $this->assertSame(0, User::query()->count());

        $_ENV['ADMIN_PASSWORD'] = 'Correct-Horse-12';
        putenv('ADMIN_PASSWORD=Correct-Horse-12');
        $this->artisan('app:create-admin', ['--no-interaction' => true])->assertSuccessful();
        $user = User::query()->where('email', 'gareth@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role->value);
        $this->assertTrue(Hash::check('Correct-Horse-12', $user->password));

        putenv('ADMIN_PASSWORD');
        unset($_ENV['ADMIN_PASSWORD'], $_ENV['ADMIN_NAME'], $_ENV['ADMIN_EMAIL']);
    }

    public function test_security_headers_and_pwa_shell(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("worker-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("manifest-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('mobile-web-app-capable', false);
        $response->assertSee('black-translucent', false);
        $response->assertSee('viewport-fit=cover', false);
        $response->assertSee('apple-touch-icon', false);
        $response->assertSee('OC Support', false);

        $manifest = $this->get('/manifest.webmanifest');
        $manifest->assertOk();
        $manifest->assertHeader('content-type', 'application/manifest+json');
        $this->assertSame('Original Concepts Support', $manifest->json('name'));
        $this->assertSame('OC Support', $manifest->json('short_name'));
        $this->assertSame('/login', $manifest->json('start_url'));
        $this->assertSame('/', $manifest->json('scope'));
        $this->assertSame('standalone', $manifest->json('display'));
        $this->assertCount(4, $manifest->json('icons'));

        $worker = $this->get('/sw.js');
        $worker->assertOk();
        $cache = (string) $worker->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cache);
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('must-revalidate', $cache);
        $worker->assertHeader('Service-Worker-Allowed', '/');
        $worker->assertSee("request.method !== 'GET'", false);
        $worker->assertSee('oc-support-', false);
        $worker->assertSee('/offline', false);

        $this->get('/offline')->assertOk()->assertSee('You are offline');
    }

    public function test_future_gateways_do_not_make_live_calls(): void
    {
        $this->assertFalse(app(OnlinePaymentGateway::class)->isConfigured());
        $this->assertFalse(app(RecurringBillingGateway::class)->isConfigured());
        $this->post('/webhooks/payfast', ['payload' => 'x'])->assertNotFound();
        $this->post('/webhooks/yoco', ['payload' => 'x'])->assertNotFound();
    }

    public function test_guest_cannot_open_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
