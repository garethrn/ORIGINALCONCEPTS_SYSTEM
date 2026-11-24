<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\View\View;

class DebtorController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(request()->user()->role->canBill(), 403);
        $rows = Invoice::query()
            ->with('client')
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0)
            ->groupBy('client_id');

        return view('debtors.index', compact('rows'));
    }
}
