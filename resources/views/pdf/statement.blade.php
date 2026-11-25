<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1206; background: #ffffff; }
        h1 { font-size: 22px; margin: 8px 0 0; border-bottom: 3px solid #f5a623; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f5a623; color: #1a1206; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #e6d3a1; padding: 6px; }
        .right { text-align: right; }
    </style>
</head>
<body>
@php
    $profile = $company->all();
    $supplier = $profile['legal_name'] !== '' ? $profile['legal_name'] : ($profile['trading_name'] ?? '');
@endphp
<img src="{{ public_path('brand/logo.png') }}" alt="Original Concepts" style="height:42px;width:auto;">
<h1>Statement</h1>
<p>{{ $supplier }}<br>{{ $client->displayName() }}<br>{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p>
<table>
    <thead><tr><th>Date</th><th>Type</th><th>Number</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead>
    <tbody>
        <tr><td colspan="5">Opening balance</td><td class="right">{{ \App\Support\Money::format($statement['opening_cents']) }}</td></tr>
        @foreach ($statement['rows'] as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['number'] }}</td>
                <td class="right">{{ $row['debit_cents'] ? \App\Support\Money::format($row['debit_cents']) : '' }}</td>
                <td class="right">{{ $row['credit_cents'] ? \App\Support\Money::format($row['credit_cents']) : '' }}</td>
                <td class="right">{{ \App\Support\Money::format($row['balance_cents']) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="5"><strong>Closing balance</strong></td><td class="right"><strong>{{ \App\Support\Money::format($statement['closing_cents']) }}</strong></td></tr>
    </tbody>
</table>
</body>
</html>
