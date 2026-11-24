<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\EmailDocument;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\SaveDraftInvoice;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Billing\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = Invoice::query()->visibleTo($request->user())->with('client')->latest()->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        return view('documents.form', $this->formData(new Invoice));
    }

    public function store(StoreInvoiceRequest $request, SaveDraftInvoice $action): RedirectResponse
    {
        $invoice = $action->handle($request->user(), $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice saved. It has no number until you issue it.');
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load('client', 'lines', 'creditNotes', 'allocations.payment');

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);
        $invoice->load('lines');

        return view('documents.form', $this->formData($invoice));
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice, SaveDraftInvoice $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice updated.');
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $action): RedirectResponse
    {
        $this->authorize('issue', $invoice);
        $request->validate(['confirm' => ['accepted']]);
        $invoice = $action->handle($request->user(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Tax invoice '.$invoice->number.' issued. It is now locked.');
    }

    public function send(Request $request, Invoice $invoice, EmailDocument $action): RedirectResponse
    {
        $this->authorize('send', $invoice);
        $request->validate(['confirm' => ['accepted']]);
        $action->handle($invoice);

        return back()->with('status', 'Invoice emailed with the PDF attached.');
    }

    public function pdf(Invoice $invoice, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($invoice->number ?: 'draft-invoice-'.$invoice->id).'.pdf"',
        ]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Draft invoice deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Invoice $invoice): array
    {
        return [
            'document' => $invoice,
            'kind' => 'invoice',
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'action' => $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store'),
            'method' => $invoice->exists ? 'PUT' : 'POST',
        ];
    }
}
