<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentType;
use App\Models\CreditNote;
use App\Models\User;
use App\Services\Accounting\Ledger;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\CompanyProfile;
use App\Services\Billing\DocumentNumberer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueCreditNote
{
    public function __construct(
        private readonly DocumentNumberer $numbers,
        private readonly CompanyProfile $company,
        private readonly InvoiceSettlement $settlement,
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, CreditNote $creditNote): CreditNote
    {
        if (! $this->company->hasVatNumber()) {
            throw ValidationException::withMessages([
                'vat' => 'Add the company VAT number in Settings before issuing a credit note.',
            ]);
        }

        return DB::transaction(function () use ($actor, $creditNote) {
            $creditNote = CreditNote::query()->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();
            if ($creditNote->status !== CreditNoteStatus::Draft) {
                throw ValidationException::withMessages(['credit_note' => 'Only a draft credit note can be issued.']);
            }

            $creditNote->number = $this->numbers->next(DocumentType::CreditNote);
            $creditNote->status = CreditNoteStatus::Issued;
            $creditNote->issued_at = now();
            $creditNote->issue_date = today();
            $creditNote->issued_by = $actor->id;
            $creditNote->save();
            $this->ledger->postCreditNote($creditNote, $actor);
            $this->settlement->refresh($creditNote->invoice);
            $this->audit->log('credit_note.issued', $creditNote, null, [
                'number' => $creditNote->number,
                'invoice_id' => $creditNote->invoice_id,
            ], $actor->id);

            return $creditNote->fresh(['lines', 'invoice', 'client']);
        });
    }
}
