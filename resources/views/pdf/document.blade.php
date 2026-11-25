<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1206; background: #ffffff; }
        h1 { color: #1a1206; font-size: 22px; margin: 8px 0 0; border-bottom: 3px solid #f5a623; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f5a623; color: #1a1206; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #e6d3a1; padding: 6px; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #5c5346; }
        .totals { width: 46%; margin-left: auto; margin-top: 12px; }
        .rule { border-top: 2px solid #f5a623; }
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
    $supplier = $profile['legal_name'] !== '' ? $profile['legal_name'] : ($profile['trading_name'] ?? '');
@endphp
<img src="{{ public_path('brand/logo.png') }}" alt="Original Concepts" style="height:42px;width:auto;">
<h1>{{ $title }}</h1>
<p>
    <strong>{{ $supplier }}</strong><br>
    @foreach ($company->addressLines() as $line) {{ $line }}<br> @endforeach
    @if (($profile['vat_number'] ?? '') !== '') VAT No: {{ $profile['vat_number'] }}<br> @endif
    {{ $profile['phone'] }}<br>
    {{ $profile['email'] }}<br>
    @if (($profile['website'] ?? '') !== '') {{ $profile['website'] }}<br> @endif
    @if (($profile['bbbee'] ?? '') !== '') {{ $profile['bbbee'] }} @endif
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
    @php $showExcl = app(\App\Services\Billing\BillingSettings::class)->showPricesExclVat(); @endphp
    <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">{{ $showExcl ? 'Value' : 'Value (incl. VAT)' }}</th><th>VAT</th><th class="right">VAT amount</th><th class="right">Total</th></tr></thead>
    <tbody>
    @foreach ($document->lines as $line)
        <tr>
            <td>{{ $line->description }}@if($line->price_includes_vat) <span class="muted">(price incl. VAT)</span>@endif
                @if ($kind === 'quote' && ($line->is_optional ?? false)) <span class="muted">(optional)</span>
                @elseif ($kind === 'quote' && ($line->option?->name)) <span class="muted">({{ $line->option->name }})</span>
                @endif
            </td>
            <td class="right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
            <td class="right">{{ \App\Support\Money::format(app(\App\Services\Billing\BillingSettings::class)->showPricesExclVat() ? $line->net_cents : $line->total_cents) }}</td>
            <td>{{ $line->vatLabel() }}</td>
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
@if ($kind === 'quote' && $document->options->isNotEmpty())
    <p>The total is {{ $document->selectedOption?->name ?? $document->options->first()?->name }}. The customer chooses one option.</p>
    <ul>
        @foreach ($document->options as $option)
            <li>{{ $option->name }}: {{ \App\Support\Money::format($option->total_cents) }}</li>
        @endforeach
    </ul>
@endif
@if ($kind === 'invoice')
    @php $timeRows = app(\App\Services\Billing\InvoiceTimeSummary::class)->rows($document); @endphp
    @if ($timeRows !== [])
        <h2 style="font-size:14px;margin-top:18px;">Time summary</h2>
        <table>
            @foreach ($timeRows as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ $row['detail'] }}</td></tr>
            @endforeach
        </table>
    @endif
@endif
@if ($document->notes)<p>{{ $document->notes }}</p>@endif
<p class="muted">{{ $profile['invoice_footer'] }}</p>
@if (($profile['bank_name'] ?? '') !== '')
    <p class="muted">Bank: {{ $profile['bank_name'] }} · Account {{ $profile['bank_account'] }} · Branch {{ $profile['branch_code'] }}</p>
@endif
</body>
</html>
