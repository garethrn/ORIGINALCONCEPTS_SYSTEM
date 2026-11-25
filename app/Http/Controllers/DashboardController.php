<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\TicketStatus;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Services\Accounting\ReportCentre;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $tickets = Ticket::query()->visibleTo($user);
        $open = (clone $tickets)->whereNotIn('status', [TicketStatus::Closed->value, TicketStatus::Cancelled->value])->count();
        $mine = (clone $tickets)->where('assigned_to', $user->id)->whereNotIn('status', [TicketStatus::Closed->value, TicketStatus::Cancelled->value])->count();
        $recent = (clone $tickets)->with('client')->latest()->limit(8)->get();

        $outstanding = 0;
        $overdue = 0;
        if ($user->role->canBill() || $user->role->value === 'client') {
            $invoices = Invoice::query()
                ->visibleTo($user)
                ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
                ->get();
            foreach ($invoices as $invoice) {
                $balance = $invoice->balanceCents();
                if ($balance > 0) {
                    $outstanding += $balance;
                    if ($invoice->isOverdue()) {
                        $overdue++;
                    }
                }
            }
        }

        $figures = $user->role->canBill() ? app(ReportCentre::class)->dashboard() : null;

        return view('dashboard', compact('open', 'mine', 'recent', 'outstanding', 'overdue', 'figures'));
    }
}
