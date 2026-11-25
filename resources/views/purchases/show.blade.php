@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $supplier->name }}</h1>
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('bills.store', $supplier) }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h2 class="font-semibold">Bill</h2>
            <input class="field" name="supplier_reference" placeholder="Supplier invoice number">
            <select class="field" name="expense_account_id">
                <option value="">Default expense account</option>
                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
            </select>
            <input class="field" name="lines[0][description]" placeholder="Description" required>
            <input class="field" name="lines[0][quantity]" value="1" required>
            <input class="field" name="lines[0][unit_price]" placeholder="Unit price excl VAT" required>
            <input type="hidden" name="lines[0][vat_code]" value="standard">
            <button class="btn btn-primary" type="submit">Save draft bill</button>
        </form>
        <form method="POST" action="{{ route('expenses.store') }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h2 class="font-semibold">Expense paid from the bank</h2>
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
            <input class="field" name="payee" value="{{ $supplier->name }}" required>
            <select class="field" name="account_id" required>
                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
            </select>
            <input class="field" type="date" name="spent_on" value="{{ today()->toDateString() }}" required>
            <input class="field" name="amount" placeholder="Amount excl VAT" required>
            <input type="hidden" name="vat_code" value="standard">
            <button class="btn btn-primary" type="submit">Post expense</button>
        </form>
    </div>
    <ul class="mt-4 space-y-2 text-sm">
        @foreach ($supplier->bills as $bill)
            <li class="rounded-2xl bg-white p-3 shadow-sm">{{ $bill->number ?: 'Draft' }} · {{ \App\Support\Money::format($bill->total_cents) }}
                @if ($bill->isDraft())
                    <form class="inline" method="POST" action="{{ route('bills.issue', $bill) }}">@csrf<button class="btn btn-ghost" type="submit">Issue</button></form>
                @endif
            </li>
        @endforeach
    </ul>
@endsection
