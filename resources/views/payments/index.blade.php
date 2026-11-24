@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">Payments</h1><a class="btn btn-gold" href="{{ route('payments.create') }}">Record payment</a></div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Method</th><th class="px-4 py-2">Amount</th></tr></thead>
            <tbody>@foreach ($payments as $payment)<tr class="border-t"><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('payments.show', $payment) }}">{{ $payment->paid_on->format('d M Y') }}</a></td><td class="px-4 py-3">{{ $payment->client?->displayName() }}</td><td class="px-4 py-3">{{ $payment->method->label() }}</td><td class="px-4 py-3">{{ \App\Support\Money::format($payment->amount_cents) }}</td></tr>@endforeach</tbody>
        </table>
    </div>
@endsection
