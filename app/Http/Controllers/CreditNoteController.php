<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\EmailDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\SaveDraftCreditNote;
use App\Http\Requests\StoreCreditNoteRequest;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\Billing\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function create(Invoice $invoice): View
    {
        $this->authorize('create', CreditNote::class);
        $this->authorize('view', $invoice);
        abort_if($invoice->isDraft(), 404);
        $invoice->load('lines', 'client');

        return view('credit-notes.form', compact('invoice'));
    }

    public function store(StoreCreditNoteRequest $request, Invoice $invoice, SaveDraftCreditNote $action): RedirectResponse
    {
        $creditNote = $action->handle($request->user(), $invoice, $request->validated());

        return redirect()->route('credit-notes.show', $creditNote)->with('status', 'Draft credit note saved.');
    }

    public function show(CreditNote $creditNote): View
    {
        $this->authorize('view', $creditNote);
        $creditNote->load('invoice', 'client', 'lines');

        return view('credit-notes.show', compact('creditNote'));
    }

    public function issue(Request $request, CreditNote $creditNote, IssueCreditNote $action): RedirectResponse
    {
        $this->authorize('issue', $creditNote);
        $request->validate(['confirm' => ['accepted']]);
        $creditNote = $action->handle($request->user(), $creditNote);

        return redirect()->route('credit-notes.show', $creditNote)->with('status', 'Credit note '.$creditNote->number.' issued against '.$creditNote->invoice->number.'.');
    }

    public function send(Request $request, CreditNote $creditNote, EmailDocument $action): RedirectResponse
    {
        $this->authorize('send', $creditNote);
        $request->validate(['confirm' => ['accepted']]);
        $action->handle($creditNote);

        return back()->with('status', 'Credit note emailed with the PDF attached.');
    }

    public function pdf(CreditNote $creditNote, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $creditNote);

        return response($pdf->render($creditNote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($creditNote->number ?: 'credit-note-'.$creditNote->id).'.pdf"',
        ]);
    }
}
