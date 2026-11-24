<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Payment::class);
        $payments = Payment::query()->with('client')->latest()->paginate(20);

        return view('payments.index', compact('payments'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);
        $clients = Client::query()->where('is_active', true)->orderBy('name')->get();
        $clientId = (int) $request->query('client');
        $invoices = $clientId
            ? Invoice::query()->where('client_id', $clientId)->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])->get()->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0)
            : collect();

        return view('payments.form', compact('clients', 'invoices', 'clientId'));
    }

    public function store(StorePaymentRequest $request, RecordPayment $action): RedirectResponse
    {
        $payment = $action->handle($request->user(), $request->validated());

        return redirect()->route('payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        $payment->load('client', 'allocations.invoice');

        return view('payments.show', compact('payment'));
    }
}
