<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentLineWriter;
use App\Services\Billing\InvoiceWorkLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDraftInvoice
{
    public function __construct(
        private readonly DocumentLineWriter $lines,
        private readonly InvoiceWorkLinks $links,
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
            $this->syncLines($invoice, $built['lines']);
            $this->audit->log($before ? 'invoice.draft_updated' : 'invoice.draft_created', $invoice, $before, $invoice->auditSnapshot(), $actor->id);

            return $invoice->load('lines', 'client');
        });
    }

    /**
     * Update posted lines in place so ticket work stays attached to the same line.
     * Links are released only for lines that were actually removed.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Invoice $invoice, array $lines): void
    {
        $existing = $invoice->lines()->get()->keyBy('id');
        $kept = [];

        foreach ($lines as $line) {
            $id = $line['source_id'] ?? null;
            unset($line['source_id']);
            if ($id && $existing->has($id) && ! isset($kept[$id])) {
                $existing[$id]->update($line);
                $kept[$id] = true;
                continue;
            }

            $invoice->lines()->create($line);
        }

        $removed = $existing->keys()->reject(fn ($id) => isset($kept[(int) $id]))->values();
        if ($removed->isNotEmpty()) {
            $this->links->release($removed);
            $invoice->lines()->whereIn('id', $removed)->delete();
        }
    }
}
