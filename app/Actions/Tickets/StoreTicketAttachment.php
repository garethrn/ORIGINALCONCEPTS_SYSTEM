<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;

final class StoreTicketAttachment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Ticket $ticket, UploadedFile $file, ?int $commentId = null): TicketAttachment
    {
        $path = $file->store('attachments', 'local');
        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'comment_id' => $commentId,
            'path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'mime' => (string) $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $actor->id,
        ]);

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'attachment',
            'note' => 'File attached: '.$attachment->original_name,
            'created_at' => now(),
        ]);
        $this->audit->log('ticket.attachment', $attachment, null, ['name' => $attachment->original_name], $actor->id);

        return $attachment;
    }

    private function safeName(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = trim($name);

        return $name === '' ? 'attachment' : mb_substr($name, 0, 180);
    }
}
