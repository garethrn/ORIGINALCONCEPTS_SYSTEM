@extends('layouts.app')
@section('content')
    <h1 class="mb-2 text-2xl font-semibold text-oc-navy">{{ $entry->memo }}</h1>
    <p class="mb-4 text-sm text-slate-500">{{ $entry->entry_date->format('d M Y') }} · {{ $entry->source_type ?: 'manual' }}</p>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Account</th><th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Credit</th></tr></thead>
            <tbody>
            @foreach ($entry->lines as $line)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                    <td class="px-4 py-3 text-right">{{ $line->debit_cents ? \App\Support\Money::format($line->debit_cents) : '' }}</td>
                    <td class="px-4 py-3 text-right">{{ $line->credit_cents ? \App\Support\Money::format($line->credit_cents) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
