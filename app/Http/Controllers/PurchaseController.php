<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\VatCode;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Accounting\Ledger;
use App\Services\Inventory\Stock;
use App\Services\Billing\DocumentLineWriter;
use App\Services\Billing\DocumentNumberer;
use App\Services\Billing\VatCalculator;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        $this->guard();
        $suppliers = Supplier::query()->orderBy('name')->paginate(20);

        return view('purchases.index', compact('suppliers'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'payment_terms_days' => ['required', 'integer', 'in:0,7,14,30,60'],
        ]);
        $supplier = Supplier::query()->create($data + ['is_active' => true]);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function show(Supplier $supplier): View
    {
        $this->guard();
        $supplier->load(['bills' => fn ($query) => $query->latest()]);
        $accounts = Account::query()->where('active', true)->where('type', 'expense')->orderBy('code')->get();
        $products = \App\Models\Product::query()->where('is_active', true)->orderBy('name')->get();

        return view('purchases.show', compact('supplier', 'accounts', 'products'));
    }

    public function storeBill(Request $request, Supplier $supplier, DocumentLineWriter $writer): RedirectResponse
    {
        $this->guard();
        $data = $this->lines($request);
        $built = $writer->build($data['lines']);
        $bill = new Bill([
            'supplier_id' => $supplier->id,
            'supplier_reference' => $data['supplier_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'expense_account_id' => $data['expense_account_id'] ?? null,
            'capital' => $request->boolean('capital'),
            'created_by' => $request->user()->id,
        ]);
        $bill->status = 'draft';
        $bill->subtotal_cents = $built['subtotal_cents'];
        $bill->vat_cents = $built['vat_cents'];
        $bill->total_cents = $built['total_cents'];
        $bill->save();
        foreach ($built['lines'] as $line) {
            unset($line['source_id']);
            $bill->lines()->create($line);
        }

        return back()->with('status', 'Draft bill saved.');
    }

    public function issueBill(Bill $bill): RedirectResponse
    {
        $this->guard();
        $this->issue($bill);

        return back()->with('status', 'Bill issued and posted to the ledger.');
    }

    public function storeOrder(Request $request, Supplier $supplier, DocumentLineWriter $writer): RedirectResponse
    {
        $this->guard();
        $data = $this->lines($request);
        $built = $writer->build($data['lines']);
        $order = new PurchaseOrder([
            'supplier_id' => $supplier->id,
            'notes' => $data['notes'] ?? null,
            'expected_on' => $data['expected_on'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $order->status = 'draft';
        $order->subtotal_cents = $built['subtotal_cents'];
        $order->vat_cents = $built['vat_cents'];
        $order->total_cents = $built['total_cents'];
        $order->save();
        foreach ($built['lines'] as $index => $line) {
            $line['product_id'] = $data['lines'][$index]['product_id'] ?? null;
            unset($line['source_id']);
            $order->lines()->create($line);
        }

        return redirect()->route('purchase-orders.show', $order)->with('status', 'Draft purchase order saved.');
    }

    public function showOrder(PurchaseOrder $order): View
    {
        $this->guard();
        $order->load('supplier', 'lines.product', 'bill');

        return view('purchases.order', compact('order'));
    }

    public function issueOrder(PurchaseOrder $order, DocumentNumberer $numbers): RedirectResponse
    {
        $this->guard();
        if ($order->isDraft()) {
            $order->number = $numbers->next(DocumentType::PurchaseOrder);
            $order->status = 'issued';
            $order->issue_date = today();
            $order->save();
        }

        return back()->with('status', 'Purchase order '.$order->number.' issued.');
    }

    public function receiveOrder(PurchaseOrder $order): RedirectResponse
    {
        $this->guard();
        if ($order->status !== 'issued') {
            return back()->with('error', 'Issue the purchase order before receiving it.');
        }
        $order->load('lines');
        $bill = new Bill([
            'supplier_id' => $order->supplier_id,
            'supplier_reference' => $order->number,
            'notes' => 'Received from '.$order->number,
            'created_by' => request()->user()->id,
        ]);
        $bill->status = 'draft';
        $bill->subtotal_cents = $order->subtotal_cents;
        $bill->vat_cents = $order->vat_cents;
        $bill->total_cents = $order->total_cents;
        $bill->save();
        foreach ($order->lines as $line) {
            $bill->lines()->create([
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'price_includes_vat' => $line->price_includes_vat,
                'vat_code' => $line->vat_code->value,
                'vat_basis_points' => $line->vat_basis_points,
                'net_cents' => $line->net_cents,
                'vat_cents' => $line->vat_cents,
                'total_cents' => $line->total_cents,
                'sort_order' => $line->sort_order,
            ]);
        }
        $bill = $this->issue($bill);
        $order->status = 'received';
        $order->bill_id = $bill->id;
        $order->save();

        return redirect()->route('purchase-orders.show', $order)->with('status', 'Goods received. Bill '.$bill->number.' was posted and stock was updated.');
    }

    private function issue(Bill $bill): Bill
    {
        return DB::transaction(function () use ($bill) {
            $bill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (! $bill->isDraft()) {
                return $bill;
            }
            $bill->number = app(DocumentNumberer::class)->next(DocumentType::Bill);
            $bill->status = 'open';
            $bill->issue_date = today();
            $bill->due_date = today()->addDays((int) $bill->supplier->payment_terms_days);
            $bill->save();
            app(Ledger::class)->postBill($bill, request()->user());
            app(Stock::class)->receiveBill($bill);

            return $bill;
        });
    }

    public function payBill(Bill $bill, Ledger $ledger): RedirectResponse
    {
        $this->guard();
        if ($bill->isDraft() || $bill->status === 'paid') {
            return back()->with('error', 'Only an open bill can be paid.');
        }
        $bank = Account::forRole('bank');
        $lines = [
            [
                'account_id' => Account::forRole('payable')->id,
                'debit_cents' => (int) $bill->total_cents,
                'credit_cents' => 0,
                'description' => 'Payment of '.$bill->number,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ],
            [
                'account_id' => $bank->id,
                'debit_cents' => 0,
                'credit_cents' => (int) $bill->total_cents,
                'description' => 'Payment of '.$bill->number,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ],
        ];
        if (app(\App\Services\Billing\BillingSettings::class)->vatBasis() === 'cash' && (int) $bill->vat_cents !== 0) {
            $lines[] = [
                'account_id' => Account::forRole('vat_input')->id,
                'debit_cents' => (int) $bill->vat_cents,
                'credit_cents' => 0,
                'description' => null,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ];
            $lines[] = [
                'account_id' => Account::forRole('vat_input_deferred')->id,
                'debit_cents' => 0,
                'credit_cents' => (int) $bill->vat_cents,
                'description' => null,
                'client_id' => null,
                'supplier_id' => $bill->supplier_id,
            ];
        }
        $ledger->post('supplier_payment', $bill->id, today()->toDateString(), 'Payment of '.$bill->number, $lines, request()->user()->id);
        $bill->status = 'paid';
        $bill->save();

        return back()->with('status', 'Bill paid and posted.');
    }

    public function storeExpense(Request $request, VatCalculator $vat, DocumentNumberer $numbers, Ledger $ledger): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'payee' => ['required', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'includes_vat' => ['nullable', 'boolean'],
            'vat_code' => ['required', Rule::enum(VatCode::class)],
            'reference' => ['nullable', 'string', 'max:80'],
            'capital' => ['nullable', 'boolean'],
        ]);
        $code = VatCode::from($data['vat_code']);
        $basis = $code === VatCode::Standard ? app(\App\Services\Billing\BillingSettings::class)->standardVatBasisPoints() : $code->basisPoints();
        $amounts = $vat->calculate('1', Money::randsToCents($data['amount']), $request->boolean('includes_vat'), $code, $basis);
        $expense = DB::transaction(function () use ($data, $request, $amounts, $code, $numbers, $ledger) {
            $expense = new Expense($data);
            $expense->number = $numbers->next(DocumentType::Expense);
            $expense->capital = $request->boolean('capital');
            $expense->created_by = $request->user()->id;
            $expense->net_cents = $amounts->netCents;
            $expense->vat_cents = $amounts->vatCents;
            $expense->total_cents = $amounts->totalCents;
            $expense->vat_basis_points = $amounts->vatBasisPoints;
            $expense->save();
            $ledger->postExpense($expense, $request->user());

            return $expense;
        });

        return back()->with('status', 'Expense '.$expense->number.' posted.');
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function lines(Request $request): array
    {
        $data = $request->validate([
            'supplier_reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expected_on' => ['nullable', 'date'],
            'expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'capital' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'lines.*.unit_price' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'lines.*.includes_vat' => ['nullable', 'boolean'],
            'lines.*.vat_code' => ['required', Rule::enum(VatCode::class)],
        ]);
        foreach ($data['lines'] as $index => $line) {
            $data['lines'][$index]['includes_vat'] = $request->boolean('lines.'.$index.'.includes_vat');
            if (($line['product_id'] ?? '') === '') {
                $data['lines'][$index]['product_id'] = null;
            }
        }

        return $data;
    }
}
