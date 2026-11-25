<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\Role;
use App\Mail\TicketMail;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Mail;

final class AddTicketComment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Ticket $ticket, string $body, bool $internal): TicketComment
    {
        if ($actor->role === Role::Client) {
            $internal = false;
        }

        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => $internal ? 'internal_note' : 'comment',
            'note' => $internal ? 'Internal note added' : 'Comment added',
            'created_at' => now(),
        ]);
        $this->audit->log('ticket.commented', $comment, null, ['internal' => $internal], $actor->id);

        $ticket->load('client', 'assignee');
        if (! $internal && $actor->isStaff() && $ticket->client?->email) {
            Mail::to($ticket->client->email)->queue(new TicketMail($ticket->id, 'comment'));
        }
        if (! $internal && ! $actor->isStaff() && $ticket->assignee?->email) {
            Mail::to($ticket->assignee->email)->queue(new TicketMail($ticket->id, 'comment'));
        }

        return $comment;
    }
}
