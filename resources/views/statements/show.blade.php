@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-oc-navy">Statement</h1>
            <div class="text-sm text-slate-600">{{ $client->displayName() }}</div>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-ghost" href="{{ route('clients.statement.pdf', [$client, 'from' => $from, 'to' => $to]) }}">PDF</a>
            <a class="btn btn-ghost" href="{{ route('clients.statement.csv', [$client, 'from' => $from, 'to' => $to]) }}">CSV</a>
        </div>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input class="field max-w-xs" type="date" name="from" value="{{ $from }}">
        <input class="field max-w-xs" type="date" name="to" value="{{ $to }}">
        <button class="btn btn-primary" type="submit">Update</button>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Number</th><th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Credit</th><th class="px-4 py-2 text-right">Balance</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-4 py-3" colspan="5">Opening balance</td><td class="px-4 py-3 text-right">{{ \App\Support\Money::format($statement['opening_cents']) }}</td></tr>
                @foreach ($statement['rows'] as $row)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $row['type'] }}</td>
                        <td class="px-4 py-3">{{ $row['number'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['debit_cents'] ? \App\Support\Money::format($row['debit_cents']) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['credit_cents'] ? \App\Support\Money::format($row['credit_cents']) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($row['balance_cents']) }}</td>
                    </tr>
                @endforeach
                <tr class="border-t font-semibold"><td class="px-4 py-3" colspan="5">Closing balance</td><td class="px-4 py-3 text-right">{{ \App\Support\Money::format($statement['closing_cents']) }}</td></tr>
            </tbody>
        </table>
    </div>
@endsection
