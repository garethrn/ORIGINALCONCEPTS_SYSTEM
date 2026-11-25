<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\VatCode;
use App\Models\Account;
use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Services\Billing\BillingSettings;
use Carbon\CarbonInterface;

final class Vat201
{
    public function __construct(private readonly BillingSettings $settings) {}

    /**
     * SARS VAT201 fields. Amounts are cents. Supply fields follow the current eFiling form:
     * standard-rated fields are VAT-inclusive and the VAT fields use the tax fraction.
     *
     * @return list<array{field: string, label: string, cents: int, section: string}>
     */
    public function fields(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = $this->settings->vatBasis() === 'cash'
            ? $this->cashOutput($from, $to)
            : $this->invoiceOutput($from, $to);
        $purchases = $this->settings->vatBasis() === 'cash'
            ? $this->cashInput($from, $to)
            : $this->invoiceInput($from, $to);

        $field4 = $sales['standard_vat'];
        $field4A = $sales['capital_vat'];
        $field9 = 0;
        $field11 = 0;
        $field12 = 0;
        $field13 = $field4 + $field4A + $field9 + $field11 + $field12;
        $field14 = $purchases['capital_vat'];
        $field15 = $purchases['other_vat'];
        $field19 = $field14 + $field15;
        $field20 = $field13 - $field19;

        return [
            ['field' => '1', 'label' => 'Standard rate (excluding capital goods and accommodation)', 'cents' => $sales['standard_inclusive'], 'section' => 'Output'],
            ['field' => '1A', 'label' => 'Standard rate (only capital goods and services)', 'cents' => $sales['capital_inclusive'], 'section' => 'Output'],
            ['field' => '2', 'label' => 'Zero rate (excluding goods exported)', 'cents' => $sales['zero'], 'section' => 'Output'],
            ['field' => '2A', 'label' => 'Zero rate (only goods exported)', 'cents' => 0, 'section' => 'Output'],
            ['field' => '3', 'label' => 'Exempt and non-supplies', 'cents' => $sales['exempt'], 'section' => 'Output'],
            ['field' => '4', 'label' => 'VAT on standard rate supplies', 'cents' => $field4, 'section' => 'Output'],
            ['field' => '4A', 'label' => 'VAT on capital supplies', 'cents' => $field4A, 'section' => 'Output'],
            ['field' => '5', 'label' => 'Accommodation exceeding 28 days', 'cents' => 0, 'section' => 'Output'],
            ['field' => '6', 'label' => 'Accommodation exceeding 28 days, 60%', 'cents' => 0, 'section' => 'Output'],
            ['field' => '7', 'label' => 'Accommodation not exceeding 28 days', 'cents' => 0, 'section' => 'Output'],
            ['field' => '8', 'label' => 'Accommodation total', 'cents' => 0, 'section' => 'Output'],
            ['field' => '9', 'label' => 'VAT on accommodation', 'cents' => $field9, 'section' => 'Output'],
            ['field' => '10', 'label' => 'Change in use and export of second-hand goods', 'cents' => 0, 'section' => 'Output'],
            ['field' => '11', 'label' => 'VAT on change in use', 'cents' => $field11, 'section' => 'Output'],
            ['field' => '12', 'label' => 'Other and imported services', 'cents' => $field12, 'section' => 'Output'],
            ['field' => '13', 'label' => 'Total output tax', 'cents' => $field13, 'section' => 'Output'],
            ['field' => '14', 'label' => 'Capital goods and services supplied to you', 'cents' => $field14, 'section' => 'Input'],
            ['field' => '14A', 'label' => 'Capital goods imported by you', 'cents' => 0, 'section' => 'Input'],
            ['field' => '15', 'label' => 'Other goods and services supplied to you', 'cents' => $field15, 'section' => 'Input'],
            ['field' => '15A', 'label' => 'Other goods imported by you', 'cents' => 0, 'section' => 'Input'],
            ['field' => '16', 'label' => 'Change in use', 'cents' => 0, 'section' => 'Input'],
            ['field' => '17', 'label' => 'Bad debts', 'cents' => 0, 'section' => 'Input'],
            ['field' => '18', 'label' => 'Other', 'cents' => 0, 'section' => 'Input'],
            ['field' => '19', 'label' => 'Total input tax', 'cents' => $field19, 'section' => 'Input'],
            ['field' => '20', 'label' => 'VAT payable or refundable', 'cents' => $field20, 'section' => 'Total'],
        ];
    }

    /**
     * @return array{standard_inclusive: int, standard_vat: int, capital_inclusive: int, capital_vat: int, zero: int, exempt: int}
     */
    private function invoiceOutput(CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = $this->blankOutput();
        $invoices = Invoice::query()
            ->with('lines')
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->get();
        foreach ($invoices as $invoice) {
            $this->addOutputLines($totals, $invoice->lines, 1);
        }
        $notes = CreditNote::query()
            ->with('lines')
            ->where('status', CreditNoteStatus::Issued->value)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->get();
        foreach ($notes as $note) {
            $this->addOutputLines($totals, $note->lines, -1);
        }

        return $totals;
    }

    /**
     * @return array{capital_vat: int, other_vat: int}
     */
    private function invoiceInput(CarbonInterface $from, CarbonInterface $to): array
    {
        $capital = 0;
        $other = 0;
        Bill::query()->where('status', '!=', 'draft')
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->get()
            ->each(function (Bill $bill) use (&$capital, &$other) {
                if ($bill->capital) {
                    $capital += (int) $bill->vat_cents;
                } else {
                    $other += (int) $bill->vat_cents;
                }
            });
        Expense::query()->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->get()
            ->each(function (Expense $expense) use (&$capital, &$other) {
                if ($expense->capital) {
                    $capital += (int) $expense->vat_cents;
                } else {
                    $other += (int) $expense->vat_cents;
                }
            });

        return ['capital_vat' => $capital, 'other_vat' => $other];
    }

    /**
     * @return array{standard_inclusive: int, standard_vat: int, capital_inclusive: int, capital_vat: int, zero: int, exempt: int}
     */
    private function cashOutput(CarbonInterface $from, CarbonInterface $to): array
    {
        $vat = $this->accountMovement('vat_output', $from, $to);
        $rate = max(1, $this->settings->standardVatBasisPoints());
        $inclusive = (int) round($vat * (10000 + $rate) / $rate);

        return [
            'standard_inclusive' => $inclusive,
            'standard_vat' => $vat,
            'capital_inclusive' => 0,
            'capital_vat' => 0,
            'zero' => 0,
            'exempt' => 0,
        ];
    }

    /**
     * @return array{capital_vat: int, other_vat: int}
     */
    private function cashInput(CarbonInterface $from, CarbonInterface $to): array
    {
        return ['capital_vat' => 0, 'other_vat' => $this->accountMovement('vat_input', $from, $to)];
    }

    private function accountMovement(string $role, CarbonInterface $from, CarbonInterface $to): int
    {
        $account = Account::forRole($role);
        $sums = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('entry', fn ($query) => $query->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString()))
            ->selectRaw('COALESCE(SUM(credit_cents), 0) as credit, COALESCE(SUM(debit_cents), 0) as debit')
            ->first();
        $credit = (int) ($sums->credit ?? 0);
        $debit = (int) ($sums->debit ?? 0);

        return $role === 'vat_input' ? $debit - $credit : $credit - $debit;
    }

    /**
     * @param  array{standard_inclusive: int, standard_vat: int, capital_inclusive: int, capital_vat: int, zero: int, exempt: int}  $totals
     */
    private function addOutputLines(array &$totals, iterable $lines, int $sign): void
    {
        foreach ($lines as $line) {
            $code = $line->vat_code instanceof VatCode ? $line->vat_code : VatCode::from((string) $line->vat_code);
            if ($code === VatCode::Zero) {
                $totals['zero'] += $sign * (int) $line->total_cents;
            } elseif ($code === VatCode::Exempt) {
                $totals['exempt'] += $sign * (int) $line->total_cents;
            } else {
                $totals['standard_inclusive'] += $sign * (int) $line->total_cents;
                $totals['standard_vat'] += $sign * (int) $line->vat_cents;
            }
        }
    }

    /**
     * @return array{standard_inclusive: int, standard_vat: int, capital_inclusive: int, capital_vat: int, zero: int, exempt: int}
     */
    private function blankOutput(): array
    {
        return [
            'standard_inclusive' => 0,
            'standard_vat' => 0,
            'capital_inclusive' => 0,
            'capital_vat' => 0,
            'zero' => 0,
            'exempt' => 0,
        ];
    }
}
