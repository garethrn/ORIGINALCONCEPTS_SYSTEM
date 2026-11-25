@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">{{ $bankAccount->name }}</h1>
        <form method="POST" action="{{ route('banking.last4', $bankAccount) }}" class="flex gap-2">
            @csrf @method('PUT')
            <input class="field max-w-[8rem]" name="account_last4" value="{{ $bankAccount->account_last4 }}" maxlength="4" placeholder="Last 4">
            <button class="btn btn-ghost" type="submit">Save</button>
        </form>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('banking.import', $bankAccount) }}" enctype="multipart/form-data" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h2 class="font-semibold">Import a statement</h2>
            <select class="field" name="format"><option value="csv">CSV</option><option value="ofx">OFX</option></select>
            <input type="file" name="statement" required>
            <p class="text-sm text-slate-500">CSV columns: date, description, amount. Amount is positive when money comes in. Debit and credit columns are accepted too.</p>
            <button class="btn btn-primary" type="submit">Import</button>
        </form>
        <form method="POST" action="{{ route('banking.rules.store', $bankAccount) }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h2 class="font-semibold">Bank rule</h2>
            <input class="field" name="contains" placeholder="Description contains" required>
            <select class="field" name="account_id" required>
                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
            </select>
            <button class="btn btn-ghost" type="submit">Save rule</button>
        </form>
    </div>
    <form method="POST" action="{{ route('banking.reconcile', $bankAccount) }}" class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        <h2 class="mb-2 font-semibold">Reconcile</h2>
        <div class="mb-3 flex flex-wrap gap-2">
            <input class="field max-w-xs" type="date" name="statement_date" value="{{ today()->toDateString() }}">
            <input class="field max-w-xs" name="statement_balance" placeholder="Statement balance">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th></th><th class="px-2 py-2">Date</th><th class="px-2 py-2">Description</th><th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2">Match</th></tr></thead>
                <tbody>
                @foreach ($transactions as $transaction)
                    <tr class="border-t">
                        <td class="py-2">@if (! $transaction->reconciliation_id)<input type="checkbox" name="transactions[]" value="{{ $transaction->id }}">@endif</td>
                        <td class="px-2 py-2">{{ $transaction->posted_on->format('d M Y') }}</td>
                        <td class="px-2 py-2">{{ $transaction->description }}</td>
                        <td class="px-2 py-2 text-right">{{ \App\Support\Money::format($transaction->amount_cents) }}</td>
                        <td class="px-2 py-2">
                            @if ($transaction->journal_line_id)
                                Matched
                            @else
                                @foreach ($suggestions[$transaction->id] ?? [] as $line)
                                    <button class="btn btn-ghost" formaction="{{ route('banking.match', $transaction) }}" formmethod="POST" name="journal_line_id" value="{{ $line->id }}" type="submit">Match {{ $line->entry?->memo }}</button>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Finish reconciliation</button>
    </form>
@endsection
