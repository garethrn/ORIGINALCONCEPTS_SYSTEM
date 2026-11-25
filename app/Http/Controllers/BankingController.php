<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankRule;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Services\Accounting\BankBook;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankingController extends Controller
{
    public function index(): View
    {
        $this->guard();
        $accounts = BankAccount::query()->with('account')->where('active', true)->orderBy('name')->get();

        return view('banking.index', compact('accounts'));
    }

    public function show(BankAccount $bankAccount): View
    {
        $this->guard();
        $transactions = $bankAccount->transactions()->with('journalLine.entry')->latest('posted_on')->latest('id')->limit(100)->get();
        $accounts = Account::query()->where('active', true)->whereIn('type', ['income', 'expense'])->orderBy('code')->get();
        $suggestions = [];
        foreach ($transactions->whereNull('journal_line_id')->take(15) as $transaction) {
            $suggestions[$transaction->id] = app(BankBook::class)->suggestions($transaction);
        }

        return view('banking.show', compact('bankAccount', 'transactions', 'accounts', 'suggestions'));
    }

    public function last4(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->guard();
        $data = $request->validate(['account_last4' => ['nullable', 'digits:4']]);
        $bankAccount->account_last4 = $data['account_last4'] ?? null;
        $bankAccount->save();

        return back()->with('status', 'Account digits saved. Bank emails that end in these digits will land here.');
    }

    public function import(Request $request, BankAccount $bankAccount, BankBook $book): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'format' => ['required', 'in:csv,ofx'],
            'statement' => ['required', 'file', 'max:2048'],
        ]);
        $created = $book->import($bankAccount, (string) file_get_contents($request->file('statement')->getRealPath()), $data['format']);

        return back()->with('status', $created.' new bank line'.($created === 1 ? '' : 's').' imported.');
    }

    public function match(Request $request, BankTransaction $transaction, BankBook $book): RedirectResponse
    {
        $this->guard();
        $data = $request->validate(['journal_line_id' => ['required', 'integer', 'exists:journal_lines,id']]);
        $book->match($transaction, JournalLine::query()->findOrFail($data['journal_line_id']));

        return back()->with('status', 'Bank line matched.');
    }

    public function rule(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'contains' => ['required', 'string', 'max:80'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);
        BankRule::query()->create([
            'bank_account_id' => $bankAccount->id,
            'contains' => $data['contains'],
            'account_id' => $data['account_id'],
        ]);

        return back()->with('status', 'Bank rule saved. It applies to the next import.');
    }

    public function reconcile(Request $request, BankAccount $bankAccount, BankBook $book): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'transactions' => ['nullable', 'array'],
            'transactions.*' => ['integer'],
        ]);
        $book->reconcile(
            $bankAccount,
            $data['statement_date'],
            Money::randsToCents($data['statement_balance']),
            $data['transactions'] ?? [],
            $request->user(),
        );

        return back()->with('status', 'Reconciliation completed.');
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }
}
