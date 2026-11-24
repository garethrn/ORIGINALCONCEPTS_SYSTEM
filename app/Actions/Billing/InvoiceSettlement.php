<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;

final class InvoiceSettlement
{
    public function refresh(Invoice $invoice): Invoice
    {
        $invoice = $invoice->fresh() ?? $invoice;
        if ($invoice->isDraft()) {
            return $invoice;
        }

        $paid = (int) $invoice->allocations()->sum('amount_cents');
        $credited = (int) $invoice->creditNotes()->where('status', CreditNoteStatus::Issued->value)->sum('total_cents');
        $balance = (int) $invoice->total_cents - $paid - $credited;

        if ($balance <= 0 && $credited >= (int) $invoice->total_cents && $paid === 0) {
            $invoice->status = InvoiceStatus::Credited;
        } elseif ($balance <= 0) {
            $invoice->status = InvoiceStatus::Paid;
        } elseif ($paid > 0 || $credited > 0) {
            $invoice->status = InvoiceStatus::PartiallyPaid;
        } else {
            $invoice->status = InvoiceStatus::Issued;
        }

        $invoice->save();

        return $invoice;
    }
}
