<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Mail\TicketMail;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class CreateTicket
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($actor, $data) {
            $priority = TicketPriority::from($data['priority']);
            $ticket = new Ticket([
                'client_id' => $data['client_id'],
                'site_id' => $data['site_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'location' => $data['location'] ?? null,
                'created_by' => $actor->id,
            ]);
            $ticket->category = TicketCategory::from($data['category']);
            $ticket->priority = $priority;
            $ticket->status = TicketStatus::Open;
            $ticket->assigned_to = $data['assigned_to'] ?? null;
            $ticket->sla_due_at = now()->addHours($priority->slaHours());
            $ticket->save();

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'action' => 'created',
                'note' => 'Ticket opened',
                'to_value' => TicketStatus::Open->value,
                'created_at' => now(),
            ]);
            $this->audit->log('ticket.created', $ticket, null, [
                'subject' => $ticket->subject,
                'client_id' => $ticket->client_id,
                'priority' => $priority->value,
            ], $actor->id);

            $ticket->load('client');
            if ($ticket->client?->email) {
                Mail::to($ticket->client->email)->queue((new TicketMail($ticket->id, 'opened'))->afterCommit());
            }

            return $ticket;
        });
    }
}
