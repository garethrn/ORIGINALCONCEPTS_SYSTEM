<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TimeEntry;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\WorkBilling;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ReportCentre
{
    public function __construct(
        private readonly Vat201 $vat201,
        private readonly BillingSettings $settings,
        private readonly WorkBilling $work,
    ) {}

    /**
     * @return array<string, list<array{key: string, title: string}>>
     */
    public function catalogue(): array
    {
        $groups = [];
        foreach ($this->definitions() as $definition) {
            $groups[$definition['category']][] = ['key' => $definition['key'], 'title' => $definition['title']];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_column($this->definitions(), 'key');
    }

    /**
     * @return array{title: string, category: string, columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    public function run(string $key, CarbonInterface $from, CarbonInterface $to, bool $compare): array
    {
        $definition = collect($this->definitions())->firstWhere('key', $key);
        if ($definition === null) {
            abort(404);
        }
        $rows = match ($key) {
            'profit-and-loss' => $this->profitAndLoss($from, $to, $compare),
            'balance-sheet' => $this->balanceSheet($to),
            'cash-flow' => $this->cashFlow($from, $to),
            'trial-balance' => $this->trialBalance($to),
            'general-ledger' => $this->generalLedger($from, $to),
            'journal' => $this->journal($from, $to),
            'aged-receivables' => $this->agedInvoices($to, true),
            'aged-payables' => $this->agedBills($to),
            'customer-balance' => $this->customerBalances(false),
            'customer-balance-detail' => $this->customerBalances(true),
            'sales-by-customer' => $this->salesByCustomer($from, $to),
            'sales-by-product' => $this->salesByProduct($from, $to),
            'sales-by-technician' => $this->salesByTechnician($from, $to),
            'invoice-list' => $this->invoiceList($from, $to, false),
            'open-invoices' => $this->invoiceList($from, $to, true),
            'unbilled-time' => $this->unbilledTime(),
            'expenses-by-supplier' => $this->expensesBySupplier($from, $to),
            'vat201' => $this->vatRows($from, $to),
            'vat-detail' => $this->vatDetail($from, $to),
            'bank-reconciliation' => $this->bankReconciliation(),
            'audit-trail' => $this->auditTrail($from, $to),
            default => [],
        };

        return [
            'title' => $definition['title'],
            'category' => $definition['category'],
            'columns' => $rows['columns'],
            'rows' => $rows['rows'],
        ];
    }

    /**
     * @return array{income_cents: int, expense_cents: int, profit_cents: int, banks: list<array{name: string, cents: int}>, overdue: int, overdue_cents: int}
     */
    public function dashboard(): array
    {
        $from = today()->startOfMonth();
        $to = today();
        $income = $this->typeTotal('income', $from, $to);
        $expense = $this->typeTotal('expense', $from, $to);
        $banks = [];
        foreach (BankAccount::query()->with('account')->where('active', true)->get() as $bank) {
            $banks[] = ['name' => $bank->name, 'cents' => $this->balance((int) $bank->account_id, $to, true)];
        }
        $overdue = Invoice::query()->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])->get()
            ->filter(fn (Invoice $invoice) => $invoice->isOverdue());

        return [
            'income_cents' => $income,
            'expense_cents' => $expense,
            'profit_cents' => $income - $expense,
            'banks' => $banks,
            'overdue' => $overdue->count(),
            'overdue_cents' => (int) $overdue->sum(fn (Invoice $invoice) => $invoice->balanceCents()),
        ];
    }

    /**
     * @return list<array{key: string, title: string, category: string}>
     */
    private function definitions(): array
    {
        return [
            ['key' => 'profit-and-loss', 'title' => 'Profit and Loss', 'category' => 'Business overview'],
            ['key' => 'balance-sheet', 'title' => 'Balance Sheet', 'category' => 'Business overview'],
            ['key' => 'cash-flow', 'title' => 'Cash Flow', 'category' => 'Business overview'],
            ['key' => 'trial-balance', 'title' => 'Trial Balance', 'category' => 'Accountant'],
            ['key' => 'general-ledger', 'title' => 'General Ledger', 'category' => 'Accountant'],
            ['key' => 'journal', 'title' => 'Journal', 'category' => 'Accountant'],
            ['key' => 'aged-receivables', 'title' => 'Aged Receivables', 'category' => 'Who owes you'],
            ['key' => 'customer-balance', 'title' => 'Customer Balance Summary', 'category' => 'Who owes you'],
            ['key' => 'customer-balance-detail', 'title' => 'Customer Balance Detail', 'category' => 'Who owes you'],
            ['key' => 'open-invoices', 'title' => 'Open Invoices', 'category' => 'Who owes you'],
            ['key' => 'invoice-list', 'title' => 'Invoice List', 'category' => 'Who owes you'],
            ['key' => 'sales-by-customer', 'title' => 'Sales by Customer', 'category' => 'Sales'],
            ['key' => 'sales-by-product', 'title' => 'Sales by Product or Service', 'category' => 'Sales'],
            ['key' => 'sales-by-technician', 'title' => 'Sales by Ticket and Technician', 'category' => 'Sales'],
            ['key' => 'unbilled-time', 'title' => 'Unbilled Ticket Time', 'category' => 'Sales'],
            ['key' => 'aged-payables', 'title' => 'Aged Payables', 'category' => 'What you owe'],
            ['key' => 'expenses-by-supplier', 'title' => 'Expenses by Supplier', 'category' => 'What you owe'],
            ['key' => 'vat201', 'title' => 'VAT201', 'category' => 'VAT'],
            ['key' => 'vat-detail', 'title' => 'VAT Detail', 'category' => 'VAT'],
            ['key' => 'bank-reconciliation', 'title' => 'Bank Reconciliation', 'category' => 'Banking'],
            ['key' => 'audit-trail', 'title' => 'Audit Trail', 'category' => 'Accountant'],
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function profitAndLoss(CarbonInterface $from, CarbonInterface $to, bool $compare): array
    {
        $months = $this->months($from, $to);
        $columns = array_merge(['Account'], $months === [] ? [] : array_map(fn (Carbon $month) => $month->format('M Y'), $months), ['Total']);
        if ($compare) {
            $columns[] = 'Previous period';
        }
        [$compareFrom, $compareTo] = $this->previous($from, $to);
        $rows = [];
        $income = 0;
        $expense = 0;
        $previousIncome = 0;
        $previousExpense = 0;
        foreach (['income' => 'Income', 'expense' => 'Expenses'] as $type => $heading) {
            $rows[] = $this->row([$heading], null, true, count($columns));
            $section = 0;
            $previousSection = 0;
            foreach (Account::query()->where('type', $type)->orderBy('code')->get() as $account) {
                $total = $this->signed($account, $from, $to);
                $section += $total;
                $cells = [$account->code.' '.$account->name];
                foreach ($months as $month) {
                    $cells[] = $this->money($this->signed($account, $month->copy()->startOfMonth()->max($from), $month->copy()->endOfMonth()->min($to)));
                }
                $cells[] = $this->money($total);
                if ($compare) {
                    $previous = $this->signed($account, $compareFrom, $compareTo);
                    $previousSection += $previous;
                    $cells[] = $this->money($previous);
                }
                if ($total !== 0 || $compare) {
                    $rows[] = $this->row($cells, route('reports.drill', ['account' => $account->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]));
                }
            }
            $totalCells = ['Total '.$heading];
            foreach ($months as $month) {
                $totalCells[] = $this->money($this->typeTotal($type, $month->copy()->startOfMonth()->max($from), $month->copy()->endOfMonth()->min($to)));
            }
            $totalCells[] = $this->money($section);
            if ($compare) {
                $totalCells[] = $this->money($previousSection);
            }
            $rows[] = $this->row($totalCells, null, true);
            if ($type === 'income') {
                $income = $section;
                $previousIncome = $previousSection;
            } else {
                $expense = $section;
                $previousExpense = $previousSection;
            }
        }
        $net = ['Net profit'];
        foreach ($months as $month) {
            $start = $month->copy()->startOfMonth()->max($from);
            $end = $month->copy()->endOfMonth()->min($to);
            $net[] = $this->money($this->typeTotal('income', $start, $end) - $this->typeTotal('expense', $start, $end));
        }
        $net[] = $this->money($income - $expense);
        if ($compare) {
            $net[] = $this->money($previousIncome - $previousExpense);
        }
        $rows[] = $this->row($net, null, true);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function balanceSheet(CarbonInterface $to): array
    {
        $rows = [];
        $assets = 0;
        $liabilities = 0;
        $equity = 0;
        foreach (['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity'] as $type => $heading) {
            $rows[] = $this->row([$heading, ''], null, true);
            $section = 0;
            foreach (Account::query()->where('type', $type)->orderBy('code')->get() as $account) {
                $balance = $this->balance($account->id, $to, $type === 'asset');
                $section += $balance;
                if ($balance === 0) {
                    continue;
                }
                $rows[] = $this->row(
                    [$account->code.' '.$account->name, $this->money($balance)],
                    route('reports.drill', ['account' => $account->id, 'to' => $to->toDateString()]),
                );
            }
            $rows[] = $this->row(['Total '.$heading, $this->money($section)], null, true);
            if ($type === 'asset') {
                $assets = $section;
            } elseif ($type === 'liability') {
                $liabilities = $section;
            } else {
                $equity = $section;
            }
        }
        $earnings = $assets - $liabilities - $equity;
        $rows[] = $this->row(['Current earnings', $this->money($earnings)], null, true);
        $rows[] = $this->row(['Total liabilities and equity', $this->money($liabilities + $equity + $earnings)], null, true);

        return ['columns' => ['Account', 'Balance'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function cashFlow(CarbonInterface $from, CarbonInterface $to): array
    {
        $income = $this->typeTotal('income', $from, $to);
        $expense = $this->typeTotal('expense', $from, $to);
        $net = $income - $expense;
        $receivable = $this->movement('receivable', $from, $to, true);
        $payable = $this->movement('payable', $from, $to, false);
        $vatOut = $this->movement('vat_output', $from, $to, false) + $this->movement('vat_output_deferred', $from, $to, false);
        $vatIn = $this->movement('vat_input', $from, $to, true) + $this->movement('vat_input_deferred', $from, $to, true);
        $inventory = $this->movement('inventory', $from, $to, true);
        $operating = $net - $receivable + $payable + $vatOut - $vatIn - $inventory;
        $opening = $this->balance(Account::forRole('bank')->id, $from->copy()->subDay(), true);
        $closing = $this->balance(Account::forRole('bank')->id, $to, true);

        return ['columns' => ['Line', 'Amount'], 'rows' => [
            $this->row(['Net profit', $this->money($net)]),
            $this->row(['Increase in accounts receivable', $this->money(-$receivable)]),
            $this->row(['Increase in accounts payable', $this->money($payable)]),
            $this->row(['Increase in VAT payable', $this->money($vatOut)]),
            $this->row(['Increase in VAT input', $this->money(-$vatIn)]),
            $this->row(['Increase in inventory', $this->money(-$inventory)]),
            $this->row(['Cash from operations', $this->money($operating)], null, true),
            $this->row(['Opening cash', $this->money($opening)]),
            $this->row(['Closing cash', $this->money($closing)], null, true),
        ]];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function trialBalance(CarbonInterface $to): array
    {
        $rows = [];
        $debit = 0;
        $credit = 0;
        foreach (Account::query()->orderBy('code')->get() as $account) {
            $raw = $this->raw($account->id, null, $to);
            $balance = (int) $raw['debit'] - (int) $raw['credit'];
            if ($balance === 0) {
                continue;
            }
            $rowDebit = $balance > 0 ? $balance : 0;
            $rowCredit = $balance < 0 ? abs($balance) : 0;
            $debit += $rowDebit;
            $credit += $rowCredit;
            $rows[] = $this->row(
                [$account->code.' '.$account->name, $this->money($rowDebit), $this->money($rowCredit)],
                route('reports.drill', ['account' => $account->id, 'to' => $to->toDateString()]),
            );
        }
        $rows[] = $this->row(['Total', $this->money($debit), $this->money($credit)], null, true);

        return ['columns' => ['Account', 'Debit', 'Credit'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function generalLedger(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $lines = JournalLine::query()->with('entry', 'account')
            ->whereHas('entry', fn ($query) => $query->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString()))
            ->get()
            ->sortBy(fn (JournalLine $line) => $line->account?->code.'|'.$line->entry?->entry_date?->toDateString().'|'.$line->id);
        $current = null;
        foreach ($lines as $line) {
            if ($current !== $line->account_id) {
                $rows[] = $this->row([$line->account?->code.' '.$line->account?->name, '', '', '', ''], null, true);
                $current = $line->account_id;
            }
            $rows[] = $this->row([
                '',
                $line->entry?->entry_date?->format('d M Y') ?? '',
                (string) $line->entry?->memo,
                $this->money((int) $line->debit_cents),
                $this->money((int) $line->credit_cents),
            ], route('journals.show', $line->journal_entry_id));
        }

        return ['columns' => ['Account', 'Date', 'Memo', 'Debit', 'Credit'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function journal(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $entries = JournalEntry::query()->with('lines.account')
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->orderBy('entry_date')->orderBy('id')->get();
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = $this->row([
                    $entry->entry_date->format('d M Y'),
                    $entry->memo,
                    $line->account?->code.' '.$line->account?->name,
                    $this->money((int) $line->debit_cents),
                    $this->money((int) $line->credit_cents),
                ], route('journals.show', $entry));
            }
        }

        return ['columns' => ['Date', 'Memo', 'Account', 'Debit', 'Credit'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function agedInvoices(CarbonInterface $to, bool $receivable): array
    {
        $rows = [];
        $invoices = Invoice::query()->with('client')->where('status', '!=', InvoiceStatus::Draft->value)->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0);
        foreach ($invoices as $invoice) {
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($to, false) : 0;
            $buckets = [0, 0, 0, 0, 0];
            $balance = $invoice->balanceCents();
            $index = $days <= 0 ? 0 : ($days <= 30 ? 1 : ($days <= 60 ? 2 : ($days <= 90 ? 3 : 4)));
            $buckets[$index] = $balance;
            $rows[] = $this->row(array_merge([
                (string) $invoice->client?->displayName(),
                (string) $invoice->number,
            ], array_map($this->money(...), $buckets), [$this->money($balance)]), route('invoices.show', $invoice));
        }

        return ['columns' => ['Customer', 'Invoice', 'Current', '1-30', '31-60', '61-90', '91+', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function agedBills(CarbonInterface $to): array
    {
        $rows = [];
        foreach (Bill::query()->with('supplier')->where('status', 'open')->get() as $bill) {
            $days = $bill->due_date ? $bill->due_date->diffInDays($to, false) : 0;
            $buckets = [0, 0, 0, 0, 0];
            $index = $days <= 0 ? 0 : ($days <= 30 ? 1 : ($days <= 60 ? 2 : ($days <= 90 ? 3 : 4)));
            $buckets[$index] = (int) $bill->total_cents;
            $rows[] = $this->row(array_merge([
                (string) $bill->supplier?->name,
                (string) $bill->number,
            ], array_map($this->money(...), $buckets), [$this->money((int) $bill->total_cents)]), route('suppliers.show', $bill->supplier_id));
        }

        return ['columns' => ['Supplier', 'Bill', 'Current', '1-30', '31-60', '61-90', '91+', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function customerBalances(bool $detail): array
    {
        $rows = [];
        $invoices = Invoice::query()->with('client')->where('status', '!=', InvoiceStatus::Draft->value)->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() !== 0)
            ->groupBy('client_id');
        foreach ($invoices as $group) {
            $client = $group->first()->client;
            if ($detail) {
                $rows[] = $this->row([(string) $client?->displayName(), '', ''], null, true);
                foreach ($group as $invoice) {
                    $rows[] = $this->row(['', (string) $invoice->number, $this->money($invoice->balanceCents())], route('invoices.show', $invoice));
                }
            }
            $rows[] = $this->row([
                $detail ? 'Total' : (string) $client?->displayName(),
                '',
                $this->money((int) $group->sum(fn (Invoice $invoice) => $invoice->balanceCents())),
            ], $client ? route('clients.statement', $client) : null, true);
        }

        return ['columns' => ['Customer', 'Invoice', 'Balance'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function salesByCustomer(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $invoices = $this->issuedInvoices($from, $to)->groupBy('client_id');
        foreach ($invoices as $group) {
            $rows[] = $this->row([
                (string) $group->first()->client?->displayName(),
                $this->money((int) $group->sum('subtotal_cents')),
                $this->money((int) $group->sum('vat_cents')),
                $this->money((int) $group->sum('total_cents')),
            ], route('clients.show', $group->first()->client_id));
        }

        return ['columns' => ['Customer', 'Exclusive', 'VAT', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function salesByProduct(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $lines = $this->issuedInvoices($from, $to)->flatMap->lines->groupBy(fn ($line) => $line->product_id ?: $line->description);
        foreach ($lines as $group) {
            $rows[] = $this->row([
                (string) $group->first()->description,
                (string) $group->sum(fn ($line) => (float) $line->quantity),
                $this->money((int) $group->sum('net_cents')),
                $this->money((int) $group->sum('total_cents')),
            ], null);
        }

        return ['columns' => ['Product or service', 'Quantity', 'Exclusive', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function salesByTechnician(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $invoices = $this->issuedInvoices($from, $to)->load('ticket.assignee');
        $groups = $invoices->groupBy(fn (Invoice $invoice) => ($invoice->ticket?->assignee?->name ?: 'No technician').'|'.($invoice->ticket?->reference() ?: 'No ticket'));
        foreach ($groups as $key => $group) {
            [$technician, $ticket] = explode('|', $key, 2);
            $rows[] = $this->row([
                $technician,
                $ticket,
                $this->money((int) $group->sum('total_cents')),
            ], $group->first()->ticket_id ? route('tickets.show', $group->first()->ticket_id) : null);
        }

        return ['columns' => ['Technician', 'Ticket', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function invoiceList(CarbonInterface $from, CarbonInterface $to, bool $openOnly): array
    {
        $rows = [];
        foreach ($this->issuedInvoices($from, $to) as $invoice) {
            if ($openOnly && $invoice->balanceCents() <= 0) {
                continue;
            }
            $rows[] = $this->row([
                (string) $invoice->number,
                (string) $invoice->client?->displayName(),
                optional($invoice->issue_date)->format('d M Y') ?? '',
                $this->money((int) $invoice->total_cents),
                $this->money($invoice->balanceCents()),
            ], route('invoices.show', $invoice));
        }

        return ['columns' => ['Invoice', 'Customer', 'Date', 'Total', 'Balance'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function unbilledTime(): array
    {
        $rows = [];
        $entries = TimeEntry::query()->with('ticket.client', 'user')->where('billable', true)->whereNull('invoice_line_id')->orderBy('work_date')->get();
        foreach ($entries as $entry) {
            $suggestion = $this->work->suggestion($entry);
            $rows[] = $this->row([
                (string) $entry->ticket?->reference(),
                (string) $entry->ticket?->client?->displayName(),
                (string) ($entry->user?->name ?: ''),
                $entry->work_date?->format('d M Y') ?? '',
                $suggestion['quantity'].' @ '.$suggestion['unit_price'],
                (string) $entry->description,
            ], $entry->ticket_id ? route('tickets.bill', $entry->ticket_id) : null);
        }

        return ['columns' => ['Ticket', 'Customer', 'Person', 'Date', 'Suggested', 'Description'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function expensesBySupplier(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $bills = Bill::query()->with('supplier')->where('status', '!=', 'draft')
            ->whereDate('issue_date', '>=', $from->toDateString())->whereDate('issue_date', '<=', $to->toDateString())->get();
        $expenses = Expense::query()->with('supplier')->whereDate('spent_on', '>=', $from->toDateString())->whereDate('spent_on', '<=', $to->toDateString())->get();
        $names = [];
        foreach ($bills as $bill) {
            $name = $bill->supplier?->name ?: 'No supplier';
            $names[$name] = ($names[$name] ?? 0) + (int) $bill->total_cents;
        }
        foreach ($expenses as $expense) {
            $name = $expense->supplier?->name ?: $expense->payee;
            $names[$name] = ($names[$name] ?? 0) + (int) $expense->total_cents;
        }
        foreach ($names as $name => $cents) {
            $rows[] = $this->row([$name, $this->money($cents)], null);
        }

        return ['columns' => ['Supplier', 'Total'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function vatRows(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        foreach ($this->vat201->fields($from, $to) as $field) {
            $rows[] = $this->row([
                $field['field'],
                $field['label'],
                $this->money($field['cents']),
            ], route('reports.show', ['report' => 'vat-detail', 'from' => $from->toDateString(), 'to' => $to->toDateString()]), $field['field'] === '13' || $field['field'] === '19' || $field['field'] === '20');
        }
        $rows[] = $this->row(['Basis', $this->settings->vatBasis() === 'cash' ? 'Cash' : 'Invoice', ''], null);

        return ['columns' => ['Field', 'Description', 'Amount'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function vatDetail(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        foreach ($this->issuedInvoices($from, $to) as $invoice) {
            $rows[] = $this->row(['Invoice', (string) $invoice->number, $this->money((int) $invoice->subtotal_cents), $this->money((int) $invoice->vat_cents)], route('invoices.show', $invoice));
        }
        foreach (Bill::query()->where('status', '!=', 'draft')->whereDate('issue_date', '>=', $from->toDateString())->whereDate('issue_date', '<=', $to->toDateString())->get() as $bill) {
            $rows[] = $this->row(['Bill', (string) $bill->number, $this->money((int) $bill->subtotal_cents), $this->money((int) $bill->vat_cents)], route('suppliers.show', $bill->supplier_id));
        }
        foreach (Expense::query()->whereDate('spent_on', '>=', $from->toDateString())->whereDate('spent_on', '<=', $to->toDateString())->get() as $expense) {
            $rows[] = $this->row(['Expense', (string) $expense->number, $this->money((int) $expense->net_cents), $this->money((int) $expense->vat_cents)], null);
        }

        return ['columns' => ['Type', 'Number', 'Exclusive', 'VAT'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function bankReconciliation(): array
    {
        $rows = [];
        $latest = BankReconciliation::query()->with('bankAccount', 'transactions')->latest('statement_date')->first();
        if ($latest === null) {
            return ['columns' => ['Line', 'Amount'], 'rows' => [$this->row(['No reconciliation yet', ''], null)]];
        }
        $rows[] = $this->row([$latest->bankAccount?->name.' statement '.$latest->statement_date->format('d M Y'), $this->money((int) $latest->statement_balance_cents)], null, true);
        foreach ($latest->transactions as $transaction) {
            $rows[] = $this->row([$transaction->posted_on->format('d M Y').' '.$transaction->description, $this->money((int) $transaction->amount_cents)], route('banking.show', $latest->bank_account_id));
        }

        return ['columns' => ['Line', 'Amount'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{cells: list<string>, href: ?string, strong: bool}>}
     */
    private function auditTrail(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [];
        $logs = AuditLog::query()->with('user')
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->orderByDesc('id')->limit(200)->get();
        foreach ($logs as $log) {
            $rows[] = $this->row([
                $log->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                (string) ($log->user?->name ?: 'System'),
                (string) $log->action,
                trim($log->subject_type.' '.$log->subject_id),
            ], null);
        }

        return ['columns' => ['When', 'Who', 'Action', 'Subject'], 'rows' => $rows];
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function issuedInvoices(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Invoice::query()->with('client', 'lines')
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->orderBy('number')
            ->get();
    }

    private function signed(Account $account, CarbonInterface $from, CarbonInterface $to): int
    {
        $raw = $this->raw($account->id, $from, $to);
        $net = (int) $raw['credit'] - (int) $raw['debit'];

        return $account->type === 'expense' ? -$net : $net;
    }

    private function typeTotal(string $type, CarbonInterface $from, CarbonInterface $to): int
    {
        $total = 0;
        foreach (Account::query()->where('type', $type)->get() as $account) {
            $total += $this->signed($account, $from, $to);
        }

        return $total;
    }

    private function balance(int $accountId, CarbonInterface $to, bool $debitNormal): int
    {
        $raw = $this->raw($accountId, null, $to);
        $net = (int) $raw['debit'] - (int) $raw['credit'];

        return $debitNormal ? $net : -$net;
    }

    private function movement(string $role, CarbonInterface $from, CarbonInterface $to, bool $debitNormal): int
    {
        $raw = $this->raw(Account::forRole($role)->id, $from, $to);
        $net = (int) $raw['debit'] - (int) $raw['credit'];

        return $debitNormal ? $net : -$net;
    }

    /**
     * @return array{debit: int, credit: int}
     */
    private function raw(int $accountId, ?CarbonInterface $from, CarbonInterface $to): array
    {
        $query = JournalLine::query()->where('account_id', $accountId)
            ->whereHas('entry', function ($query) use ($from, $to) {
                $query->whereDate('entry_date', '<=', $to->toDateString());
                if ($from !== null) {
                    $query->whereDate('entry_date', '>=', $from->toDateString());
                }
            });
        $sums = $query->selectRaw('COALESCE(SUM(debit_cents), 0) as debit, COALESCE(SUM(credit_cents), 0) as credit')->first();

        return ['debit' => (int) ($sums->debit ?? 0), 'credit' => (int) ($sums->credit ?? 0)];
    }

    /**
     * @return list<Carbon>
     */
    private function months(CarbonInterface $from, CarbonInterface $to): array
    {
        if ($from->format('Y-m') === $to->format('Y-m')) {
            return [];
        }
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function previous(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $compareTo = $from->copy()->subDay();

        return [$compareTo->copy()->subDays($days - 1), $compareTo];
    }

    private function money(int $cents): string
    {
        return $cents === 0 ? '' : Money::format($cents);
    }

    /**
     * @param  list<string>  $cells
     * @return array{cells: list<string>, href: ?string, strong: bool}
     */
    private function row(array $cells, ?string $href = null, bool $strong = false, int $width = 0): array
    {
        if ($width > count($cells)) {
            $cells = array_pad($cells, $width, '');
        }

        return ['cells' => $cells, 'href' => $href, 'strong' => $strong];
    }
}
