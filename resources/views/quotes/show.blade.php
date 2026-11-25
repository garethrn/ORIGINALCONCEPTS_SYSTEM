@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Quotation {{ $quote->number }}</h1>
        <a class="btn btn-ghost" href="{{ route('quotes.pdf', $quote) }}">PDF</a>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="font-semibold">{{ $quote->client?->displayName() }}</div>
        <div class="text-sm text-slate-600"><x-status :value="$quote->status" /> · Valid until {{ optional($quote->valid_until)->format('d M Y') ?: '—' }}</div>
        <div class="mt-3 text-lg font-semibold">{{ \App\Support\Money::format($quote->total_cents) }}</div>
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($quote->lines as $line)
                <li>{{ $line->description }} · {{ $line->quantity }} · {{ \App\Support\Money::format($line->total_cents) }}
                    @if ($line->is_optional)<span class="text-slate-500">· optional{{ $line->option_accepted ? ', included' : '' }}</span>
                    @elseif ($line->option)<span class="text-slate-500">· {{ $line->option->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($quote->options->isNotEmpty())
            <div class="mt-4 grid gap-2 md:grid-cols-2">
                @foreach ($quote->options as $option)
                    <div class="rounded-xl border border-slate-200 p-3 {{ (int) $quote->selected_option_id === (int) $option->id ? 'ring-2 ring-amber-400' : '' }}">
                        <div class="font-medium">{{ $option->name }}</div>
                        <div>{{ \App\Support\Money::format($option->total_cents) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @can('accept', $quote)
        @if ($quote->options->isNotEmpty() || $quote->lines->contains(fn ($line) => $line->is_optional))
            <form method="POST" action="{{ route('quotes.choose', $quote) }}" class="mt-4 space-y-3 rounded-2xl bg-white p-4 shadow-sm">
                @csrf
                <h2 class="font-semibold">Choose what to accept</h2>
                @if ($quote->options->isNotEmpty())
                    @foreach ($quote->options as $option)
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="option_id" value="{{ $option->id }}" @checked((int) $quote->selected_option_id === (int) $option->id) required> {{ $option->name }} · {{ \App\Support\Money::format($option->total_cents) }}</label>
                    @endforeach
                @endif
                @foreach ($quote->lines->where('is_optional', true) as $line)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="optional_lines[]" value="{{ $line->id }}" @checked($line->option_accepted)> Add {{ $line->description }} · {{ \App\Support\Money::format($line->total_cents) }}</label>
                @endforeach
                <button class="btn btn-primary" type="submit">Accept this choice</button>
            </form>
        @endif
    @endcan
    <div class="mt-4 flex flex-wrap gap-2">
        @can('update', $quote)<a class="btn btn-ghost" href="{{ route('quotes.edit', $quote) }}">Edit draft</a>@endcan
        @can('issue', $quote)
            <form method="POST" action="{{ route('quotes.issue', $quote) }}">@csrf<label class="mr-2 text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this quote should be issued</label><button class="btn btn-primary" type="submit">Issue quote</button></form>
        @endcan
        @can('send', $quote)
            <form method="POST" action="{{ route('quotes.send', $quote) }}">@csrf<label class="mr-2 text-sm"><input type="checkbox" name="confirm" value="1" required> I confirm this email</label><button class="btn btn-ghost" type="submit">Email PDF</button></form>
        @endcan
        @can('accept', $quote)
            @if ($quote->options->isEmpty())
                <form method="POST" action="{{ route('quotes.decision', $quote) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="btn btn-gold" type="submit">Accept</button></form>
            @endif
            <form method="POST" action="{{ route('quotes.decision', $quote) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-ghost" type="submit">Reject</button></form>
        @endcan
        @can('convert', $quote)
            <form method="POST" action="{{ route('quotes.convert', $quote) }}">@csrf<button class="btn btn-primary" type="submit">Convert to draft invoice</button></form>
        @endcan
    </div>
@endsection
