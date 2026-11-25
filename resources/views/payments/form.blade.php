@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Record a payment</h1>
    <form class="mb-4 flex flex-wrap gap-2" method="GET">
        <select class="field max-w-md" name="client"><option value="">Choose a customer</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->displayName() }}</option>@endforeach</select>
        <button class="btn btn-ghost" type="submit">Show invoices</button>
    </form>
    @if ($clientId)
        <form method="POST" action="{{ route('payments.store') }}" class="space-y-3 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <input type="hidden" name="client_id" value="{{ $clientId }}">
            <input class="field" name="amount" placeholder="Amount in rands" required>
            <input class="field" type="date" name="paid_on" value="{{ now()->toDateString() }}" required>
            <select class="field" name="method">@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select>
            <input class="field" name="reference" placeholder="Reference">
            <div class="space-y-2">
                @foreach ($invoices as $index => $invoice)
                    <label class="flex flex-wrap items-center gap-2 text-sm">{{ $invoice->number }} balance {{ \App\Support\Money::format($invoice->balanceCents()) }}
                        <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice->id }}">
                        <input class="field max-w-40" name="allocations[{{ $index }}][amount]" placeholder="Allocate">
                    </label>
                @endforeach
            </div>
            <button class="btn btn-primary" type="submit">Save payment</button>
        </form>
    @endif
@endsection
