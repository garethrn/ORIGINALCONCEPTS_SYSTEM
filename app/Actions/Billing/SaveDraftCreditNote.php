<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentLineWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDraftCreditNote
{
    public function __construct(
        private readonly DocumentLineWriter $lines,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Invoice $invoice, array $data, ?CreditNote $creditNote = null): CreditNote
    {
        if ($invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Credit a issued tax invoice, not a draft.']);
        }

        return DB::transaction(function () use ($actor, $invoice, $data, $creditNote) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($creditNote && ! $creditNote->isDraft()) {
                throw ValidationException::withMessages(['credit_note' => 'Issued credit notes are locked.']);
            }

            $built = $this->lines->build($data['lines']);
            $otherCredits = (int) $invoice->creditNotes()
                ->where('status', CreditNoteStatus::Issued->value)
                ->when($creditNote, fn ($query) => $query->whereKeyNot($creditNote->id))
                ->sum('total_cents');
            $paid = (int) $invoice->allocations()->sum('amount_cents');
            $remaining = (int) $invoice->total_cents - $paid - $otherCredits;
            if ($built['total_cents'] > $remaining) {
                throw ValidationException::withMessages([
                    'lines' => 'The credit note cannot exceed the outstanding invoice balance.',
                ]);
            }

            $creditNote ??= new CreditNote([
                'created_by' => $actor->id,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
            ]);
            $creditNote->status = CreditNoteStatus::Draft;
            $creditNote->notes = $data['notes'] ?? null;
            $creditNote->subtotal_cents = $built['subtotal_cents'];
            $creditNote->vat_cents = $built['vat_cents'];
            $creditNote->total_cents = $built['total_cents'];
            $creditNote->save();
            $creditNote->lines()->delete();
            $creditNote->lines()->createMany($built['lines']);
            $this->audit->log('credit_note.draft_saved', $creditNote, null, [
                'invoice_id' => $invoice->id,
                'total_cents' => $creditNote->total_cents,
            ], $actor->id);

            return $creditNote->load('lines', 'invoice');
        });
    }
}
