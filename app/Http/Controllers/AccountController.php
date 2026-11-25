<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $this->guard();
        $accounts = Account::query()->orderBy('code')->get()->groupBy('type');

        return view('accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        $this->guard();

        return view('accounts.form', ['account' => new Account(['type' => 'expense', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard();
        Account::query()->create($this->validated($request));

        return redirect()->route('accounts.index')->with('status', 'Account saved.');
    }

    public function edit(Account $account): View
    {
        $this->guard();

        return view('accounts.form', compact('account'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->guard();
        $account->update($this->validated($request, $account));

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        if ($account?->isSystem()) {
            unset($data['code'], $data['type']);
        }

        return $data;
    }
}
