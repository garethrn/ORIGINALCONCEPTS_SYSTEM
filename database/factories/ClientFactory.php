<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'trading_name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'vat_number' => '4'.fake()->numerify('#########'),
            'payment_terms_days' => 30,
            'address_line1' => fake()->streetAddress(),
            'city' => 'Johannesburg',
            'province' => 'Gauteng',
            'postal_code' => '2000',
            'country' => 'South Africa',
            'is_active' => true,
        ];
    }
}
