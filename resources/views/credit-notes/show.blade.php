@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-oc-navy">{{ $creditNote->isDraft() ? 'Draft credit note' : 'Credit Note' }} {{ $creditNote->number }}</h1>
        <a class="btn btn-ghost" href="{{ route('credit-notes.pdf', $creditNote) }}">PDF</a>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p>This credit note refers to tax invoice <a class="text-oc-blue" href="{{ route('invoices.show', $creditNote->invoice) }}">{{ $creditNote->invoice?->number }}</a> issued on {{ optional($creditNote->invoice?->issue_date)->format('d M Y') }}.</p>
        <p class="mt-2">Customer {{ $creditNote->client?->name }} · VAT {{ $creditNote->client?->vat_number ?: '—' }}</p>
        <p class="mt-2 text-lg font-semibold">{{ \App\Support\Money::format($creditNote->total_cents) }}</p>
        <ul class="mt-3 text-sm">
            @foreach ($creditNote->lines as $line)<li>{{ $line->description }} · {{ \App\Support\Money::format($line->total_cents) }}</li>@endforeach
        </ul>
    </div>
    @can('issue', $creditNote)
        <form class="mt-4" method="POST" action="{{ route('credit-notes.issue', $creditNote) }}">@csrf<label class="mr-2 text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this credit note should be issued</label><button class="btn btn-primary" type="submit">Issue credit note</button></form>
    @endcan
@endsection
