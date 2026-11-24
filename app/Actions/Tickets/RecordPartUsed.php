<?php

declare(strict_types=1);

namespace App\Actions\Tickets;

use App\Enums\VatCode;
use App\Models\PartUsed;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;

final class RecordPartUsed
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Ticket $ticket, array $data): PartUsed
    {
        $product = isset($data['product_id']) ? Product::query()->find($data['product_id']) : null;
        $price = isset($data['unit_price']) && $data['unit_price'] !== ''
            ? Money::randsToCents((string) $data['unit_price'])
            : (int) ($product?->unit_price_cents ?? 0);

        $part = PartUsed::query()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product?->id,
            'description' => $data['description'] ?? $product?->name,
            'quantity' => $data['quantity'],
            'unit_price_cents' => $price,
            'price_includes_vat' => (bool) ($data['includes_vat'] ?? $product?->price_includes_vat ?? false),
            'vat_code' => VatCode::from($data['vat_code'] ?? $product?->vat_code?->value ?? VatCode::Standard->value),
            'billable' => (bool) ($data['billable'] ?? true),
        ]);

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'part',
            'note' => 'Part recorded: '.$part->description,
            'created_at' => now(),
        ]);
        $this->audit->log('ticket.part', $part, null, ['description' => $part->description], $actor->id);

        return $part;
    }
}
