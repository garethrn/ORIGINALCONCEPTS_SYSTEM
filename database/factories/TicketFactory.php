<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => TicketCategory::ItSupport,
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'sla_due_at' => now()->addDay(),
        ];
    }
}
