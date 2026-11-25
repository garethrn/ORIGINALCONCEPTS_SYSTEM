@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-oc-navy">Purchase order {{ $order->number ?: 'draft' }}</h1>
            <div class="text-sm text-slate-600">{{ $order->supplier?->name }} · {{ ucfirst($order->status) }}</div>
        </div>
        <a class="btn btn-ghost" href="{{ route('suppliers.show', $order->supplier_id) }}">Supplier</a>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <ul class="space-y-1 text-sm">
            @foreach ($order->lines as $line)
                <li>{{ $line->description }} · {{ $line->quantity }} · {{ \App\Support\Money::format($line->total_cents) }}@if($line->product?->track_stock) · stock {{ $line->product->quantity_on_hand }}@endif</li>
            @endforeach
        </ul>
        <div class="mt-3 font-semibold">{{ \App\Support\Money::format($order->total_cents) }}</div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        @if ($order->isDraft())
            <form method="POST" action="{{ route('purchase-orders.issue', $order) }}">@csrf<button class="btn btn-primary" type="submit">Issue order</button></form>
        @elseif ($order->status === 'issued')
            <form method="POST" action="{{ route('purchase-orders.receive', $order) }}">@csrf<button class="btn btn-primary" type="submit">Receive goods</button></form>
        @elseif ($order->bill)
            <p class="text-sm">Received on bill {{ $order->bill->number }}.</p>
        @endif
    </div>
@endsection
