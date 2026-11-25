<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Accounting\Ledger;
use App\Services\Inventory\Stock;
use App\Services\Billing\CompanyProfile;
use App\Services\Billing\DocumentNumberer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueInvoice
{
    public function __construct(
        private readonly DocumentNumberer $numbers,
        private readonly CompanyProfile $company,
        private readonly Ledger $ledger,
        private readonly Stock $stock,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Invoice $invoice): Invoice
    {
        if (! $this->company->hasVatNumber()) {
            throw ValidationException::withMessages([
                'vat' => 'Add the company VAT number in Settings before issuing a tax invoice.',
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'invoice' => 'Only a draft invoice can be issued.',
                ]);
            }
            if ($invoice->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => 'Add at least one line before issuing.']);
            }

            $before = $invoice->auditSnapshot();
            $invoice->number = $this->numbers->next(DocumentType::Invoice);
            $invoice->status = InvoiceStatus::Issued;
            $invoice->issued_at = now();
            $invoice->issue_date = today();
            $terms = (int) ($invoice->client()->value('payment_terms_days') ?? 30);
            $invoice->due_date = today()->addDays($terms);
            $invoice->issued_by = $actor->id;
            $invoice->save();
            $this->ledger->postInvoice($invoice, $actor);
            $this->stock->receiveInvoice($invoice);
            $this->audit->log('invoice.issued', $invoice, $before, $invoice->auditSnapshot(), $actor->id);

            return $invoice->fresh(['lines', 'client']);
        });
    }
}
