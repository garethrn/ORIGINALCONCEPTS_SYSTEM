<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use App\Services\Billing\CompanyProfile;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role] + $attributes);
    }

    protected function companyVat(): void
    {
        app(CompanyProfile::class)->update([
            'legal_name' => 'Original Concepts (Pty) Ltd',
            'address_line1' => '1 Main Road',
            'city' => 'Johannesburg',
            'country' => 'South Africa',
            'vat_number' => '4123456789',
            'email' => 'support@originalconcepts.co.za',
        ]);
    }
}
