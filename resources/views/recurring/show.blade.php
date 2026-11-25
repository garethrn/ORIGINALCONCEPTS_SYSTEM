@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-oc-navy">{{ $schedule->name }}</h1>
            <div class="text-sm text-slate-600">{{ $schedule->client?->displayName() }} · {{ $schedule->frequency->label() }} · Next {{ $schedule->next_on->format('d M Y') }}</div>
        </div>
        <a class="btn btn-ghost" href="{{ route('recurring.edit', $schedule) }}">Edit</a>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-sm">{{ $schedule->active ? 'Active' : 'Paused' }} · {{ $schedule->auto_issue ? 'Invoices are issued automatically' : 'Each run creates a draft' }}{{ $schedule->ends_on ? ' · Ends '.$schedule->ends_on->format('d M Y') : '' }}</p>
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($schedule->lines as $line)
                <li>{{ $line->description }} · {{ $line->quantity }} · {{ \App\Support\Money::format($line->total_cents) }}</li>
            @endforeach
        </ul>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('recurring.run', $schedule) }}">@csrf<button class="btn btn-primary" type="submit">Create if due</button></form>
        <form method="POST" action="{{ route('recurring.destroy', $schedule) }}">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit">Remove</button></form>
    </div>
@endsection
