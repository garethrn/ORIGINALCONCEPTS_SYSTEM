<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\QuotePackages;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertQuoteToInvoice
{
    public function __construct(
        private readonly SaveDraftInvoice $drafts,
        private readonly QuotePackages $packages,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Quote $quote): Invoice
    {
        if (! in_array($quote->status, [QuoteStatus::Issued, QuoteStatus::Accepted], true)) {
            throw ValidationException::withMessages([
                'quote' => 'Issue the quote before converting it to an invoice.',
            ]);
        }

        return DB::transaction(function () use ($actor, $quote) {
            $quote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($quote->status === QuoteStatus::Converted) {
                throw ValidationException::withMessages(['quote' => 'This quote has already been converted.']);
            }

            $quote->load('lines', 'options');
            if ($this->packages->requiresChoice($quote) && $quote->selected_option_id === null) {
                throw ValidationException::withMessages([
                    'quote' => 'Choose an option before converting this quote.',
                ]);
            }
            $chosen = $this->packages->invoiceLines($quote);
            if ($chosen->isEmpty()) {
                throw ValidationException::withMessages(['quote' => 'The accepted quote has no lines to invoice.']);
            }

            $invoice = $this->drafts->handle($actor, [
                'client_id' => $quote->client_id,
                'contact_id' => $quote->contact_id,
                'ticket_id' => $quote->ticket_id,
                'quote_id' => $quote->id,
                'notes' => $quote->notes,
                'lines' => $chosen->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => Money::centsToRands((int) $line->unit_price_cents),
                    'includes_vat' => (bool) $line->price_includes_vat,
                    'vat_code' => $line->vat_code->value,
                ])->all(),
            ]);

            $quote->status = QuoteStatus::Converted;
            $quote->invoice_id = $invoice->id;
            $quote->save();
            $this->audit->log('quote.converted', $quote, null, ['invoice_id' => $invoice->id], $actor->id);

            return $invoice;
        });
    }
}
