<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankFeedNotice;
use App\Models\BankMailbox;
use App\Models\BankTransaction;
use App\Models\Bill;
use App\Services\Banking\TransactionMatcher;
use App\Services\Banking\TransactionReviewer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankFeedController extends Controller
{
    public function edit(): View
    {
        $this->guard();
        $mailbox = BankMailbox::query()->first() ?? new BankMailbox([
            'name' => 'Bank alerts',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'auto_allocate' => true,
            'confidence_threshold' => 90,
            'active' => true,
        ]);

        return view('banking.feed', compact('mailbox'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'imap_host' => ['nullable', 'string', 'max:150'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['required', 'in:ssl,tls,none'],
            'imap_username' => ['nullable', 'string', 'max:150'],
            'imap_password' => ['nullable', 'string', 'max:200'],
            'imap_folder' => ['required', 'string', 'max:80'],
            'forwarding_address' => ['nullable', 'email', 'max:150'],
            'allowed_senders' => ['nullable', 'string', 'max:2000'],
            'auto_allocate' => ['nullable', 'boolean'],
            'confidence_threshold' => ['required', 'integer', 'min:50', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);
        $mailbox = BankMailbox::query()->first() ?? new BankMailbox;
        $password = $data['imap_password'] ?? null;
        unset($data['imap_password']);
        $mailbox->fill($data);
        $mailbox->auto_allocate = $request->boolean('auto_allocate');
        $mailbox->active = $request->boolean('active');
        if ($password !== null && $password !== '') {
            $mailbox->imap_password = $password;
        }
        $mailbox->save();

        return back()->with('status', 'Bank feed saved. The password is stored encrypted.');
    }

    public function review(): View
    {
        $this->guard();
        $transactions = BankTransaction::query()
            ->with('bankAccount', 'email')
            ->whereNotNull('bank_email_id')
            ->whereIn('review_status', ['pending', 'allocated'])
            ->latest('posted_on')
            ->limit(50)
            ->get();
        $accounts = Account::query()->where('active', true)->where('type', 'expense')->orderBy('code')->get();
        $notices = BankFeedNotice::query()->latest()->limit(8)->get();

        return view('banking.review', compact('transactions', 'accounts', 'notices'));
    }

    public function accept(BankTransaction $transaction, TransactionMatcher $matcher, TransactionReviewer $reviewer): RedirectResponse
    {
        $this->guard();
        $suggestion = $matcher->suggest($transaction);
        if (count($suggestion['invoice_ids']) === 1) {
            $reviewer->allocateInvoices($transaction, $suggestion['invoice_ids'], request()->user());
        } elseif ($suggestion['bill_id'] !== null) {
            $reviewer->payBill($transaction, Bill::query()->findOrFail($suggestion['bill_id']));
        } elseif ($suggestion['rule_id'] !== null) {
            $rule = \App\Models\BankRule::query()->findOrFail($suggestion['rule_id']);
            $reviewer->assignExpense($transaction, (int) $rule->account_id);
        } else {
            return back()->with('error', 'There is no single match to accept. Split it or assign an account.');
        }

        return back()->with('status', 'Transaction allocated.');
    }

    public function split(Request $request, BankTransaction $transaction, TransactionReviewer $reviewer): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'lines.*.amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $ids = [];
        $amounts = [];
        foreach ($data['lines'] as $line) {
            if (empty($line['invoice_id']) || ($line['amount'] ?? '') === '') {
                continue;
            }
            $ids[] = (int) $line['invoice_id'];
            $amounts[(int) $line['invoice_id']] = Money::randsToCents($line['amount']);
        }
        $reviewer->allocateInvoices($transaction, $ids, $request->user(), $amounts);

        return back()->with('status', 'Transaction split across the invoices.');
    }

    public function expense(Request $request, BankTransaction $transaction, TransactionReviewer $reviewer): RedirectResponse
    {
        $this->guard();
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:accounts,id']]);
        $reviewer->assignExpense($transaction, (int) $data['account_id']);

        return back()->with('status', 'Transaction posted to the expense account.');
    }

    public function ignore(BankTransaction $transaction, TransactionReviewer $reviewer): RedirectResponse
    {
        $this->guard();
        $reviewer->ignore($transaction);

        return back()->with('status', 'Transaction ignored.');
    }

    public function undo(BankTransaction $transaction, TransactionReviewer $reviewer): RedirectResponse
    {
        $this->guard();
        $reviewer->undo($transaction);

        return back()->with('status', 'Allocation undone. The transaction is back in the review list.');
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }
}
