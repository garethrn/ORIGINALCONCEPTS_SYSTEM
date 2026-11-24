<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\ConvertQuoteToInvoice;
use App\Actions\Billing\EmailDocument;
use App\Actions\Billing\IssueQuote;
use App\Actions\Billing\SaveDraftQuote;
use App\Enums\QuoteStatus;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);
        $quotes = Quote::query()->visibleTo($request->user())->with('client')->latest()->paginate(20);

        return view('quotes.index', compact('quotes'));
    }

    public function create(): View
    {
        $this->authorize('create', Quote::class);

        return view('documents.form', $this->formData(new Quote));
    }

    public function store(StoreQuoteRequest $request, SaveDraftQuote $action): RedirectResponse
    {
        $quote = $action->handle($request->user(), $request->validated());

        return redirect()->route('quotes.show', $quote)->with('status', 'Draft quote saved.');
    }

    public function show(Quote $quote): View
    {
        $this->authorize('view', $quote);
        $quote->load('client', 'lines', 'contact');

        return view('quotes.show', compact('quote'));
    }

    public function edit(Quote $quote): View
    {
        $this->authorize('update', $quote);
        $quote->load('lines');

        return view('documents.form', $this->formData($quote));
    }

    public function update(StoreQuoteRequest $request, Quote $quote, SaveDraftQuote $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $quote);

        return redirect()->route('quotes.show', $quote)->with('status', 'Draft quote updated.');
    }

    public function issue(Request $request, Quote $quote, IssueQuote $action): RedirectResponse
    {
        $this->authorize('issue', $quote);
        $request->validate(['confirm' => ['accepted']]);
        $quote = $action->handle($request->user(), $quote);

        return redirect()->route('quotes.show', $quote)->with('status', 'Quote '.$quote->number.' issued.');
    }

    public function send(Request $request, Quote $quote, EmailDocument $action): RedirectResponse
    {
        $this->authorize('send', $quote);
        $request->validate(['confirm' => ['accepted']]);
        $action->handle($quote);

        return back()->with('status', 'Quote emailed with the PDF attached.');
    }

    public function accept(Request $request, Quote $quote, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('accept', $quote);
        $quote->status = $request->input('decision') === 'reject' ? QuoteStatus::Rejected : QuoteStatus::Accepted;
        $quote->save();
        $audit->log('quote.'.$quote->status->value, $quote);

        return back()->with('status', 'Quote marked '.$quote->status->label().'.');
    }

    public function convert(Quote $quote, ConvertQuoteToInvoice $action): RedirectResponse
    {
        $this->authorize('convert', $quote);
        $invoice = $action->handle(request()->user(), $quote);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created from the quote. Issue it when you are ready.');
    }

    public function pdf(Quote $quote, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $quote);

        return response($pdf->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($quote->number ?: 'quote-'.$quote->id).'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Quote $quote): array
    {
        return [
            'document' => $quote,
            'kind' => 'quote',
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'action' => $quote->exists ? route('quotes.update', $quote) : route('quotes.store'),
            'method' => $quote->exists ? 'PUT' : 'POST',
        ];
    }
}
