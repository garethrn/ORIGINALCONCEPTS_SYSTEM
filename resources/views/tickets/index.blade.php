@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Tickets</h1>
        <a class="btn btn-gold" href="{{ route('tickets.create') }}">New ticket</a>
    </div>
    <form class="mb-4 flex gap-2" method="GET">
        <input class="field" name="q" value="{{ request('q') }}" placeholder="Search subject">
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <div class="space-y-3 md:hidden">
        @foreach ($tickets as $ticket)
            <a class="block rounded-2xl bg-white p-4 shadow-sm" href="{{ route('tickets.show', $ticket) }}">
                <div class="text-xs text-slate-500">{{ $ticket->reference() }} · {{ $ticket->priority->label() }}</div>
                <div class="font-semibold">{{ $ticket->subject }}</div>
                <div class="text-sm">{{ $ticket->client?->displayName() }} · {{ $ticket->status->label() }}</div>
            </a>
        @endforeach
    </div>
    <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Ref</th><th class="px-4 py-2">Subject</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Priority</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody>
            @foreach ($tickets as $ticket)
                <tr class="border-t"><td class="px-4 py-3">{{ $ticket->reference() }}</td><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td><td class="px-4 py-3">{{ $ticket->client?->displayName() }}</td><td class="px-4 py-3">{{ $ticket->priority->label() }}</td><td class="px-4 py-3">{{ $ticket->status->label() }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection
