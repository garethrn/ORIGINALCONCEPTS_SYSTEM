@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Recurring invoices</h1>
        <a class="btn btn-primary" href="{{ route('recurring.create') }}">New schedule</a>
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Every</th><th class="px-4 py-2">Next</th><th class="px-4 py-2">Mode</th></tr></thead>
            <tbody>
            @forelse ($schedules as $schedule)
                <tr class="border-t">
                    <td class="px-4 py-3"><a href="{{ route('recurring.show', $schedule) }}">{{ $schedule->name }}</a></td>
                    <td class="px-4 py-3">{{ $schedule->client?->displayName() }}</td>
                    <td class="px-4 py-3">{{ $schedule->frequency->label() }}</td>
                    <td class="px-4 py-3">{{ $schedule->active ? $schedule->next_on->format('d M Y') : 'Paused' }}</td>
                    <td class="px-4 py-3">{{ $schedule->auto_issue ? 'Issue automatically' : 'Create a draft' }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-slate-500" colspan="5">No recurring invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $schedules->links() }}</div>
@endsection
