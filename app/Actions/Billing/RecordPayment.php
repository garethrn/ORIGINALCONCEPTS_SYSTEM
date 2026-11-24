<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPayment
{
    public function __construct(
        private readonly InvoiceSettlement $settlement,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($actor, $data) {
            $amount = Money::randsToCents((string) $data['amount']);
            $allocated = 0;
            $rows = [];

            foreach ($data['allocations'] ?? [] as $row) {
                $cents = Money::randsToCents((string) ($row['amount'] ?? '0'));
                if ($cents === 0) {
                    continue;
                }
                $invoice = Invoice::query()->whereKey($row['invoice_id'])->lockForUpdate()->firstOrFail();
                if ((int) $invoice->client_id !== (int) $data['client_id']) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Every allocation has to belong to the same customer as the payment.',
                    ]);
                }
                if ($invoice->isDraft()) {
                    throw ValidationException::withMessages(['allocations' => 'Draft invoices cannot take a payment.']);
                }
                if ($cents > $invoice->balanceCents()) {
                    throw ValidationException::withMessages([
                        'allocations' => 'An allocation is larger than the invoice balance.',
                    ]);
                }
                $allocated += $cents;
                $rows[] = [$invoice, $cents];
            }

            if ($allocated > $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Allocations cannot exceed the payment amount.',
                ]);
            }

            $payment = new Payment([
                'client_id' => $data['client_id'],
                'paid_on' => $data['paid_on'],
                'method' => PaymentMethod::from($data['method']),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $payment->amount_cents = $amount;
            $payment->save();

            foreach ($rows as [$invoice, $cents]) {
                $payment->allocations()->create([
                    'invoice_id' => $invoice->id,
                    'amount_cents' => $cents,
                ]);
                $this->settlement->refresh($invoice);
            }

            $this->audit->log('payment.recorded', $payment, null, [
                'amount_cents' => $amount,
                'allocated_cents' => $allocated,
                'method' => $payment->method->value,
            ], $actor->id);

            return $payment->load('allocations.invoice');
        });
    }
}
