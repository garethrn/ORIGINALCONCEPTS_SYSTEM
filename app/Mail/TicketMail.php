<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public int $ticketId, public string $event) {}

    public function envelope(): Envelope
    {
        $ticket = Ticket::query()->findOrFail($this->ticketId);
        $subject = match ($this->event) {
            'opened' => 'Ticket '.$ticket->reference().' opened',
            'comment' => 'New reply on ticket '.$ticket->reference(),
            default => 'Ticket '.$ticket->reference().' updated',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ticket',
            with: [
                'ticket' => Ticket::query()->with('client')->findOrFail($this->ticketId),
                'event' => $this->event,
            ],
        );
    }
}
