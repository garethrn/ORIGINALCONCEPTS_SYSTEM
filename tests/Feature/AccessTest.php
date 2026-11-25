<?php

namespace Tests\Feature;

use App\Actions\Tickets\CreateTicket;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_portal_uses_the_customer_link_not_the_user_id(): void
    {
        User::factory()->create();
        $customer = Client::factory()->create();
        $decoy = Client::factory()->create();
        $other = Client::factory()->create();
        $portal = $this->userWithRole(Role::Client, ['client_id' => $customer->id]);
        $own = Ticket::factory()->create(['client_id' => $customer->id, 'subject' => 'Own printer']);
        $foreign = Ticket::factory()->create(['client_id' => $other->id, 'subject' => 'Foreign secret']);
        $trap = Ticket::factory()->create(['client_id' => $decoy->id, 'subject' => 'User id trap']);
        $this->assertSame($portal->id, $decoy->id);
        $this->assertNotSame($portal->client_id, $decoy->id);

        TicketComment::query()->create([
            'ticket_id' => $own->id,
            'user_id' => $this->admin()->id,
            'body' => 'SECRET-INTERNAL-NOTE',
            'is_internal' => true,
        ]);

        $this->actingAs($portal)->get(route('tickets.show', $own))->assertOk()->assertSee('Own printer')->assertDontSee('SECRET-INTERNAL-NOTE');
        $this->actingAs($portal)->get(route('tickets.show', $foreign))->assertForbidden();
        $this->actingAs($portal)->get(route('tickets.show', $trap))->assertForbidden();
        $this->actingAs($portal)->get(route('invoices.index'))->assertOk();
        $this->actingAs($portal)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_private_attachments_are_authorized(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/secret.txt', 'secret-bytes');
        $customer = Client::factory()->create();
        $portal = $this->userWithRole(Role::Client, ['client_id' => $customer->id]);
        $ticket = Ticket::factory()->create(['client_id' => $customer->id]);
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'body' => 'internal file',
            'is_internal' => true,
        ]);
        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'path' => 'attachments/secret.txt',
            'original_name' => 'secret.txt',
            'mime' => 'text/plain',
            'size' => 12,
        ]);

        $this->actingAs($portal)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
        $this->actingAs($this->admin())->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertOk();
    }

    public function test_ticket_status_changes_follow_the_workflow(): void
    {
        $tech = $this->userWithRole(Role::Technician);
        $ticket = app(CreateTicket::class)->handle($tech, [
            'client_id' => Client::factory()->create()->id,
            'subject' => 'Printer offline',
            'description' => 'The floor printer does not respond.',
            'category' => 'printing',
            'priority' => 'high',
        ]);

        $this->actingAs($tech)->post(route('tickets.transition', $ticket), [
            'status' => TicketStatus::Closed->value,
        ])->assertSessionHasErrors('status');
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);

        $this->actingAs($tech)->post(route('tickets.transition', $ticket), [
            'status' => TicketStatus::InProgress->value,
        ])->assertRedirect();
        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->sla_due_at);
    }

    public function test_technician_cannot_create_an_invoice(): void
    {
        $this->actingAs($this->userWithRole(Role::Technician))
            ->get(route('invoices.create'))
            ->assertForbidden();
    }
}
