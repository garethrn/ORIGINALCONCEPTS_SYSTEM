<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentLineWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDraftInvoice
{
    public function __construct(
        private readonly DocumentLineWriter $lines,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($actor, $data, $invoice) {
            if ($invoice && ! $invoice->isDraft()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Issued invoices are locked. Raise a credit note to correct one.',
                ]);
            }

            $built = $this->lines->build($data['lines']);
            $invoice ??= new Invoice(['created_by' => $actor->id]);
            $before = $invoice->exists ? $invoice->auditSnapshot() : null;
            $invoice->fill([
                'client_id' => $data['client_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? $invoice->quote_id,
                'notes' => $data['notes'] ?? null,
            ]);
            $invoice->status = InvoiceStatus::Draft;
            $invoice->subtotal_cents = $built['subtotal_cents'];
            $invoice->vat_cents = $built['vat_cents'];
            $invoice->total_cents = $built['total_cents'];
            $invoice->save();
            $invoice->lines()->delete();
            $invoice->lines()->createMany($built['lines']);
            $this->audit->log($before ? 'invoice.draft_updated' : 'invoice.draft_created', $invoice, $before, $invoice->auditSnapshot(), $actor->id);

            return $invoice->load('lines', 'client');
        });
    }
}
