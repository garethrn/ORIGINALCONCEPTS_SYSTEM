@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">Invoices</h1>@can('create', App\Models\Invoice::class)<a class="btn btn-gold" href="{{ route('invoices.create') }}">New invoice</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Number</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Total</th></tr></thead>
            <tbody>
            @foreach ($invoices as $invoice)
                <tr class="border-t"><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number ?: 'Draft' }}</a></td><td class="px-4 py-3">{{ $invoice->client?->displayName() }}</td><td class="px-4 py-3"><x-status :value="$invoice->status" />@if($invoice->isOverdue()) <x-status value="Overdue" />@endif</td><td class="px-4 py-3">{{ \App\Support\Money::format($invoice->total_cents) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
