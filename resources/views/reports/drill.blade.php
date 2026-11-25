@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Transactions</h1>
    <p class="mb-4 text-sm text-slate-500">{{ $from ?: 'Start' }} to {{ $to ?: 'today' }}</p>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Account</th><th class="px-4 py-2">Memo</th><th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Credit</th></tr></thead>
            <tbody>
            @foreach ($lines as $line)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $line->entry?->entry_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                    <td class="px-4 py-3"><a href="{{ route('journals.show', $line->journal_entry_id) }}">{{ $line->entry?->memo }}</a></td>
                    <td class="px-4 py-3 text-right">{{ $line->debit_cents ? \App\Support\Money::format($line->debit_cents) : '' }}</td>
                    <td class="px-4 py-3 text-right">{{ $line->credit_cents ? \App\Support\Money::format($line->credit_cents) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
