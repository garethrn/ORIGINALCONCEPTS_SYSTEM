<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentLineWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDraftQuote
{
    public function __construct(
        private readonly DocumentLineWriter $lines,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?Quote $quote = null): Quote
    {
        return DB::transaction(function () use ($actor, $data, $quote) {
            if ($quote && ! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'This quote can no longer be edited.']);
            }

            $built = $this->lines->build($data['lines']);
            $quote ??= new Quote(['created_by' => $actor->id]);
            $quote->fill([
                'client_id' => $data['client_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);
            $quote->status = QuoteStatus::Draft;
            $quote->subtotal_cents = $built['subtotal_cents'];
            $quote->vat_cents = $built['vat_cents'];
            $quote->total_cents = $built['total_cents'];
            $quote->save();
            $quote->lines()->delete();
            $quote->lines()->createMany($built['lines']);
            $this->audit->log('quote.draft_saved', $quote, null, ['total_cents' => $quote->total_cents], $actor->id);

            return $quote->load('lines', 'client');
        });
    }
}
