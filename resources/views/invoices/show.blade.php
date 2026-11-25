@extends('layouts.app')
@php
    $company = app(\App\Services\Billing\CompanyProfile::class);
    $showExcl = app(\App\Services\Billing\BillingSettings::class)->showPricesExclVat();
    $timeRows = app(\App\Services\Billing\InvoiceTimeSummary::class)->rows($invoice);
@endphp
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">{{ $invoice->isDraft() ? 'Draft invoice' : 'Tax Invoice' }} {{ $invoice->number }}</h1>
        <a class="btn btn-ghost" href="{{ route('invoices.pdf', $invoice) }}">PDF</a>
    </div>
    <div class="grid gap-4 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        <div>
            <div class="text-xs uppercase text-slate-500">Supplier</div>
            <div class="font-semibold">{{ $company->get('legal_name') }}</div>
            @foreach ($company->addressLines() as $line)<div>{{ $line }}</div>@endforeach
            <div>VAT No: {{ $company->get('vat_number') ?: 'Not set' }}</div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-500">Customer</div>
            <div class="font-semibold">{{ $invoice->client?->name }}</div>
            @foreach ($invoice->client?->addressLines() ?? [] as $line)<div>{{ $line }}</div>@endforeach
            <div>VAT No: {{ $invoice->client?->vat_number ?: '—' }}</div>
        </div>
    </div>
    <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Description</th><th class="px-4 py-2">Qty</th><th class="px-4 py-2">{{ $showExcl ? 'Value' : 'Value (incl. VAT)' }}</th><th class="px-4 py-2">VAT</th><th class="px-4 py-2">Total</th></tr></thead>
            <tbody>
            @foreach ($invoice->lines as $line)
                <tr class="border-t"><td class="px-4 py-3">{{ $line->description }}</td><td class="px-4 py-3">{{ $line->quantity }}</td><td class="px-4 py-3">{{ \App\Support\Money::format($showExcl ? $line->net_cents : $line->total_cents) }}</td><td class="px-4 py-3">{{ $line->vatLabel() }} {{ \App\Support\Money::format($line->vat_cents) }}</td><td class="px-4 py-3">{{ \App\Support\Money::format($line->total_cents) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 text-right">
            <div>Exclusive {{ \App\Support\Money::format($invoice->subtotal_cents) }}</div>
            <div>VAT {{ \App\Support\Money::format($invoice->vat_cents) }}</div>
            <div class="text-lg font-semibold">Total {{ \App\Support\Money::format($invoice->total_cents) }}</div>
            @unless($invoice->isDraft())<div>Balance {{ \App\Support\Money::format($invoice->balanceCents()) }}</div>@endunless
        </div>
    </div>
    @if ($timeRows !== [])
        <section class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-2 font-semibold">Time summary</h2>
            <ul class="space-y-1 text-sm">
                @foreach ($timeRows as $row)
                    <li><span class="font-medium">{{ $row['label'] }}</span> · {{ $row['detail'] }}</li>
                @endforeach
            </ul>
        </section>
    @endif
    <div class="mt-4 text-sm text-slate-600">Serial {{ $invoice->number ?: 'assigned on issue' }} · Date {{ optional($invoice->issue_date)->format('d M Y') ?: '—' }} · Due {{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</div>
    @unless ($invoice->isDraft())
        <p class="mt-2 text-sm text-slate-600">This invoice is locked. Corrections are made with a credit note.</p>
    @endunless
    <div class="mt-4 flex flex-wrap gap-2">
        @can('update', $invoice)<a class="btn btn-ghost" href="{{ route('invoices.edit', $invoice) }}">Edit draft</a>@endcan
        @can('issue', $invoice)
            <form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <label class="text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this tax invoice should be issued</label>
                <button class="btn btn-primary" type="submit">Issue invoice</button>
            </form>
        @endcan
        @can('send', $invoice)
            <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <label class="text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this email</label>
                <button class="btn btn-ghost" type="submit">Email PDF</button>
            </form>
        @endcan
        @can('create', App\Models\CreditNote::class)
            @unless($invoice->isDraft())<a class="btn btn-ghost" href="{{ route('credit-notes.create', $invoice) }}">Credit note</a>@endunless
        @endcan
    </div>
@endsection
