<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;

final class DocumentPdf
{
    public function render(Invoice|Quote|CreditNote $document): string
    {
        $document->loadMissing(['client', 'contact', 'lines']);
        if ($document instanceof CreditNote) {
            $document->loadMissing('invoice');
        }

        $kind = match ($document::class) {
            Invoice::class => 'invoice',
            Quote::class => 'quote',
            CreditNote::class => 'credit_note',
        };

        return Pdf::loadView('pdf.document', [
            'document' => $document,
            'company' => app(CompanyProfile::class),
            'kind' => $kind,
        ])->setPaper('a4')->setOption('isRemoteEnabled', false)->output();
    }
}
