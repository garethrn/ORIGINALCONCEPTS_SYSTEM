@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Journal</h1>
        <a class="btn btn-primary" href="{{ route('journals.create') }}">Manual journal</a>
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Memo</th><th class="px-4 py-2">Source</th><th class="px-4 py-2 text-right">Debit</th></tr></thead>
            <tbody>
            @foreach ($entries as $entry)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $entry->entry_date->format('d M Y') }}</td>
                    <td class="px-4 py-3"><a href="{{ route('journals.show', $entry) }}">{{ $entry->memo }}</a></td>
                    <td class="px-4 py-3">{{ $entry->source_type ?: 'manual' }}</td>
                    <td class="px-4 py-3 text-right">{{ \App\Support\Money::format((int) $entry->lines->sum('debit_cents')) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
