@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Dashboard</h1>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-500">Open tickets</div><div class="text-3xl font-semibold">{{ $open }}</div></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-500">Assigned to me</div><div class="text-3xl font-semibold">{{ $mine }}</div></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-500">Outstanding</div><div class="text-3xl font-semibold">{{ \App\Support\Money::format($outstanding) }}</div></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-500">Overdue invoices</div><div class="text-3xl font-semibold">{{ $overdue }}</div></div>
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
