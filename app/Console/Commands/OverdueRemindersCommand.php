<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\DocumentMail;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class OverdueRemindersCommand extends Command
{
    protected $signature = 'support:overdue-reminders';

    protected $description = 'Email a reminder for overdue invoices that still have a balance.';

    public function handle(): int
    {
        $sent = 0;
        Invoice::query()
            ->with('client')
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', today())
            ->where(function ($query) {
                $query->whereNull('overdue_reminded_at')->orWhere('overdue_reminded_at', '<', now()->subDays(7));
            })
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$sent) {
                if ($invoice->balanceCents() <= 0 || ! $invoice->client?->email || ! $invoice->number) {
                    return;
                }
                Mail::to($invoice->client->email)->queue(new DocumentMail(Invoice::class, $invoice->id));
                $invoice->overdue_reminded_at = now();
                $invoice->save();
                $sent++;
            });

        $this->info($sent.' overdue reminder(s) queued.');

        return self::SUCCESS;
    }
}
