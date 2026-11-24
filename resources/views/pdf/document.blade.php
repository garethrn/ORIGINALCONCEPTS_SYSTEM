<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { color: #1a3c5e; font-size: 22px; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #1a3c5e; color: #fff; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #ddd; padding: 6px; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #555; }
        .totals { width: 46%; margin-left: auto; margin-top: 12px; }
    </style>
</head>
<body>
@php
    $profile = $company->all();
    $title = match ($kind) {
        'invoice' => $document->isDraft() ? 'Draft invoice' : 'Tax Invoice',
        'quote' => $document->isDraft() ? 'Draft quotation' : 'Quotation',
        default => $document->isDraft() ? 'Draft credit note' : 'Credit Note',
    };
    $customer = $document->client;
@endphp
<h1>{{ $title }}</h1>
<p>
    <strong>{{ $profile['legal_name'] }}</strong><br>
    @foreach ($company->addressLines() as $line) {{ $line }}<br> @endforeach
    VAT No: {{ $profile['vat_number'] !== '' ? $profile['vat_number'] : 'Not set' }}<br>
    {{ $profile['email'] }} {{ $profile['phone'] }}
</p>
<p>
    <strong>{{ $customer?->name }}</strong>
    @if ($customer?->trading_name && $customer->trading_name !== $customer->name)<br>Trading as {{ $customer->trading_name }}@endif
    <br>
    @foreach ($customer?->addressLines() ?? [] as $line) {{ $line }}<br> @endforeach
    Customer VAT No: {{ $customer?->vat_number ?: '—' }}
</p>
<p>
    Serial No: {{ $document->number ?: 'Not issued' }}<br>
    Date: {{ optional($document->issue_date)->format('d M Y') ?: '—' }}<br>
    @if ($kind === 'invoice') Due: {{ optional($document->due_date)->format('d M Y') ?: '—' }}<br> @endif
    @if ($kind === 'credit_note')
        This credit note refers to tax invoice {{ $document->invoice?->number }} issued on {{ optional($document->invoice?->issue_date)->format('d M Y') }}.
    @endif
</p>
<table>
    <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Value</th><th>VAT</th><th class="right">VAT amount</th><th class="right">Total</th></tr></thead>
    <tbody>
    @foreach ($document->lines as $line)
        <tr>
            <td>{{ $line->description }}@if($line->price_includes_vat) <span class="muted">(price incl. VAT)</span>@endif</td>
            <td class="right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
            <td class="right">{{ \App\Support\Money::format($line->net_cents) }}</td>
            <td>{{ $line->vat_code->shortLabel() }}</td>
            <td class="right">{{ \App\Support\Money::format($line->vat_cents) }}</td>
            <td class="right">{{ \App\Support\Money::format($line->total_cents) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    <tr><td>Exclusive value</td><td class="right">{{ \App\Support\Money::format($document->subtotal_cents) }}</td></tr>
    <tr><td>VAT</td><td class="right">{{ \App\Support\Money::format($document->vat_cents) }}</td></tr>
    <tr><td><strong>Total</strong></td><td class="right"><strong>{{ \App\Support\Money::format($document->total_cents) }}</strong></td></tr>
</table>
@if ($document->notes)<p>{{ $document->notes }}</p>@endif
<p class="muted">{{ $profile['invoice_footer'] }}</p>
@if (($profile['bank_name'] ?? '') !== '')
    <p class="muted">Bank: {{ $profile['bank_name'] }} · Account {{ $profile['bank_account'] }} · Branch {{ $profile['branch_code'] }}</p>
@endif
</body>
</html>
