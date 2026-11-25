<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Mail\TicketMail;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class TransitionTicket
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Ticket $ticket, TicketStatus $next, ?int $assignedTo = null): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $next, $assignedTo) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $ticket->status;
            if ($from !== $next && ! $from->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => 'That status change is not allowed from '.$from->label().'.',
                ]);
            }

            $before = ['status' => $from->value, 'assigned_to' => $ticket->assigned_to];
            if ($from !== $next) {
                $ticket->status = $next;
                $ticket->resolved_at = $next === TicketStatus::Resolved ? now() : $ticket->resolved_at;
                $ticket->closed_at = $next === TicketStatus::Closed ? now() : ($next === TicketStatus::InProgress ? null : $ticket->closed_at);
                TicketEvent::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $actor->id,
                    'action' => 'status_changed',
                    'note' => 'Status changed from '.$from->label().' to '.$next->label(),
                    'from_value' => $from->value,
                    'to_value' => $next->value,
                    'created_at' => now(),
                ]);
            }

            if ($assignedTo !== null && (int) $ticket->assigned_to !== $assignedTo) {
                $ticket->assigned_to = $assignedTo;
                TicketEvent::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $actor->id,
                    'action' => 'assigned',
                    'note' => 'Assignment updated',
                    'to_value' => (string) $assignedTo,
                    'created_at' => now(),
                ]);
            }

            $ticket->save();
            $this->audit->log('ticket.updated', $ticket, $before, [
                'status' => $ticket->status->value,
                'assigned_to' => $ticket->assigned_to,
            ], $actor->id);

            $ticket->load('client');
            if ($from !== $next && $ticket->client?->email) {
                Mail::to($ticket->client->email)->queue((new TicketMail($ticket->id, 'updated'))->afterCommit());
            }

            return $ticket;
        });
    }
}
