@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Chart of accounts</h1>
        <div class="flex gap-2">
            <a class="btn btn-ghost" href="{{ route('journals.index') }}">Journals</a>
            <a class="btn btn-primary" href="{{ route('accounts.create') }}">New account</a>
        </div>
    </div>
    @foreach (['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'] as $type => $label)
        <section class="mb-4 overflow-x-auto rounded-2xl bg-white shadow-sm">
            <h2 class="px-4 pt-4 font-semibold">{{ $label }}</h2>
            <table class="min-w-full text-sm">
                <tbody>
                @foreach ($accounts[$type] ?? [] as $account)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $account->code }}</td>
                        <td class="px-4 py-3"><a href="{{ route('accounts.edit', $account) }}">{{ $account->name }}</a></td>
                        <td class="px-4 py-3 text-slate-500">{{ $account->active ? '' : 'Inactive' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
@endsection
