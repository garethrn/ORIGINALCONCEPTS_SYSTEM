@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Dashboard</h1>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card rounded-2xl bg-white p-4"><div class="text-xs uppercase tracking-wide">Open tickets</div><div class="text-3xl font-semibold">{{ $open }}</div></div>
        <div class="stat-card rounded-2xl bg-white p-4"><div class="text-xs uppercase tracking-wide">Assigned to me</div><div class="text-3xl font-semibold">{{ $mine }}</div></div>
        <div class="stat-card rounded-2xl bg-white p-4"><div class="text-xs uppercase tracking-wide">Outstanding</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($outstanding) }}</div></div>
        <div class="stat-card rounded-2xl bg-white p-4"><div class="text-xs uppercase tracking-wide">Overdue invoices</div><div class="text-3xl font-semibold">{{ $overdue }}</div></div>
        @if ($figures)
            <a class="stat-card rounded-2xl bg-white p-4" href="{{ route('reports.show', 'profit-and-loss') }}"><div class="text-xs uppercase tracking-wide">Income this month</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($figures['income_cents']) }}</div></a>
            <a class="stat-card rounded-2xl bg-white p-4" href="{{ route('reports.show', 'profit-and-loss') }}"><div class="text-xs uppercase tracking-wide">Expenses this month</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($figures['expense_cents']) }}</div></a>
            <a class="stat-card rounded-2xl bg-white p-4" href="{{ route('reports.show', 'profit-and-loss') }}"><div class="text-xs uppercase tracking-wide">Net profit</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($figures['profit_cents']) }}</div></a>
            @foreach ($figures['banks'] as $bank)
                <a class="stat-card rounded-2xl bg-white p-4" href="{{ route('banking.index') }}"><div class="text-xs uppercase tracking-wide">{{ $bank['name'] }}</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($bank['cents']) }}</div></a>
            @endforeach
        @endif
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="flex items-center justify-between px-4 py-3"><h2 class="font-semibold">Recent tickets</h2><a class="btn btn-gold" href="{{ route('tickets.create') }}">New ticket</a></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Ref</th><th class="px-4 py-2">Subject</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Status</th></tr></thead>
                <tbody>
                @forelse ($recent as $ticket)
                    <tr class="border-t"><td class="px-4 py-3">{{ $ticket->reference() }}</td><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td><td class="px-4 py-3">{{ $ticket->client?->displayName() }}</td><td class="px-4 py-3">{{ $ticket->status->label() }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-slate-500" colspan="4">No tickets yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
