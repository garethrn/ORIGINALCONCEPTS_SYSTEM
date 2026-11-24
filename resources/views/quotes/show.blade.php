@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Quotation {{ $quote->number }}</h1>
        <a class="btn btn-ghost" href="{{ route('quotes.pdf', $quote) }}">PDF</a>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="font-semibold">{{ $quote->client?->displayName() }}</div>
        <div class="text-sm text-slate-600">Status {{ $quote->status->label() }} · Valid until {{ optional($quote->valid_until)->format('d M Y') ?: '—' }}</div>
        <div class="mt-3 text-lg font-semibold">{{ \App\Support\Money::format($quote->total_cents) }}</div>
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($quote->lines as $line)<li>{{ $line->description }} · {{ $line->quantity }} · {{ \App\Support\Money::format($line->total_cents) }}</li>@endforeach
        </ul>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        @can('update', $quote)<a class="btn btn-ghost" href="{{ route('quotes.edit', $quote) }}">Edit draft</a>@endcan
        @can('issue', $quote)
            <form method="POST" action="{{ route('quotes.issue', $quote) }}">@csrf<label class="mr-2 text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this quote should be issued</label><button class="btn btn-primary" type="submit">Issue quote</button></form>
        @endcan
        @can('send', $quote)
            <form method="POST" action="{{ route('quotes.send', $quote) }}">@csrf<label class="mr-2 text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this email</label><button class="btn btn-ghost" type="submit">Email PDF</button></form>
        @endcan
        @can('accept', $quote)
            <form method="POST" action="{{ route('quotes.decision', $quote) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="btn btn-gold" type="submit">Accept</button></form>
            <form method="POST" action="{{ route('quotes.decision', $quote) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-ghost" type="submit">Reject</button></form>
        @endcan
        @can('convert', $quote)
            <form method="POST" action="{{ route('quotes.convert', $quote) }}">@csrf<button class="btn btn-primary" type="submit">Convert to draft invoice</button></form>
        @endcan
    </div>
@endsection
