<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class JournalController extends Controller
{
    public function index(): View
    {
        $this->guard();
        $entries = JournalEntry::query()->with('lines')->latest('entry_date')->latest('id')->paginate(30);

        return view('journals.index', compact('entries'));
    }

    public function create(): View
    {
        $this->guard();

        return view('journals.form', ['accounts' => Account::query()->where('active', true)->orderBy('code')->get()]);
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.credit' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
        ]);
        $lines = [];
        foreach ($data['lines'] as $line) {
            $debit = \App\Support\Money::randsToCents((string) (($line['debit'] ?? '') !== '' ? $line['debit'] : '0'));
            $credit = \App\Support\Money::randsToCents((string) (($line['credit'] ?? '') !== '' ? $line['credit'] : '0'));
            if (($line['account_id'] ?? null) === null && $debit === 0 && $credit === 0) {
                continue;
            }
            if (($line['account_id'] ?? null) === null) {
                throw ValidationException::withMessages(['lines' => 'Choose an account for every amount.']);
            }
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(['lines' => 'A line is either a debit or a credit.']);
            }
            $lines[] = [
                'account_id' => (int) $line['account_id'],
                'debit_cents' => $debit,
                'credit_cents' => $credit,
                'description' => $line['description'] ?? null,
                'client_id' => null,
                'supplier_id' => null,
            ];
        }
        try {
            $entry = $ledger->postManual($data['entry_date'], $data['memo'], $lines, $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('journals.show', $entry)->with('status', 'Journal saved.');
    }

    public function show(JournalEntry $entry): View
    {
        $this->guard();
        $entry->load('lines.account', 'author');

        return view('journals.show', compact('entry'));
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }
}
