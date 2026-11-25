<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1206; background: #ffffff; }
        h1 { font-size: 20px; border-bottom: 3px solid #f5a623; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f3f4f6; color: #1f2937; text-align: left; padding: 5px; border-bottom: 2px solid #f5a623; }
        td { border-bottom: 1px solid #e6d3a1; padding: 5px; }
    </style>
</head>
<body>
@php $profile = $company->all(); @endphp
<img src="{{ public_path('brand/logo.png') }}" alt="Original Concepts" style="height:36px;width:auto;">
<h1>{{ $title }}</h1>
<p>{{ $profile['legal_name'] !== '' ? $profile['legal_name'] : ($profile['trading_name'] ?? '') }} · {{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p>
<table>
    <thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>@foreach ($row['cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
