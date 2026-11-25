@extends('layouts.app')
@section('content')
    <h1 class="mb-2 text-2xl font-semibold text-oc-navy">{{ \App\Support\Money::format($payment->amount_cents) }}</h1>
    <p class="mb-4 text-sm">{{ $payment->client?->displayName() }} · {{ $payment->method->label() }} · {{ $payment->paid_on->format('d M Y') }} · {{ $payment->reference }}</p>
    <div class="rounded-2xl bg-white p-4 shadow-sm text-sm">
        <div>Unallocated {{ \App\Support\Money::format($payment->unallocatedCents()) }}</div>
        <ul class="mt-2">@foreach ($payment->allocations as $allocation)<li>{{ $allocation->invoice?->number }} · {{ \App\Support\Money::format($allocation->amount_cents) }}</li>@endforeach</ul>
    </div>
@endsection
