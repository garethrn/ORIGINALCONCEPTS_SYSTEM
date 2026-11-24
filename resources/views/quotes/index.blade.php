@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">Quotes</h1>@can('create', App\Models\Quote::class)<a class="btn btn-gold" href="{{ route('quotes.create') }}">New quote</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Number</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Total</th></tr></thead>
            <tbody>
            @foreach ($quotes as $quote)
                <tr class="border-t"><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('quotes.show', $quote) }}">{{ $quote->number ?: 'Draft' }}</a></td><td class="px-4 py-3">{{ $quote->client?->displayName() }}</td><td class="px-4 py-3">{{ $quote->status->label() }}</td><td class="px-4 py-3">{{ \App\Support\Money::format($quote->total_cents) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $quotes->links() }}</div>
@endsection
