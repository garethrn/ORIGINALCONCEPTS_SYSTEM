@extends('layouts.app')
@section('content')
    <div class="mb-2 text-sm text-slate-500">{{ $ticket->reference() }} · {{ $ticket->category->label() }} · {{ $ticket->priority->label() }}</div>
    <h1 class="mb-2 text-2xl font-semibold text-oc-navy">{{ $ticket->subject }}</h1>
    <div class="mb-4 text-sm">{{ $ticket->client?->displayName() }} · {{ $ticket->status->label() }} · SLA {{ optional($ticket->sla_due_at)->timezone(config('app.timezone'))->format('d M Y H:i') }}</div>
    <div class="rounded-2xl bg-white p-4 shadow-sm whitespace-pre-wrap">{{ $ticket->description }}</div>
    @if ($ticket->site?->directionsUrl())
        <a class="mt-3 inline-block text-sm text-oc-blue" href="{{ $ticket->site->directionsUrl() }}">Directions to {{ $ticket->site->name }}</a>
    @endif

    @can('update', $ticket)
        <form method="POST" action="{{ route('tickets.transition', $ticket) }}" class="mt-4 flex flex-wrap gap-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <select class="field max-w-xs" name="status">
                <option value="{{ $ticket->status->value }}">{{ $ticket->status->label() }} (current)</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
            </select>
            <select class="field max-w-xs" name="assigned_to">
                <option value="">Keep assignment</option>
                @foreach ($staff as $person)<option value="{{ $person->id }}" @selected($ticket->assigned_to == $person->id)>{{ $person->name }}</option>@endforeach
            </select>
            <button class="btn btn-primary" type="submit">Update</button>
        </form>
    @endcan

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold">Timeline</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($ticket->events as $event)
                    <li><span class="text-slate-500">{{ $event->created_at->format('d M H:i') }}</span> {{ $event->note }} @if($event->user) · {{ $event->user->name }} @endif</li>
                @endforeach
            </ul>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold">Comments</h2>
            @foreach ($ticket->comments as $comment)
                <div class="mb-3 border-b pb-2 text-sm">
                    <div class="text-xs text-slate-500">{{ $comment->user?->name }} · {{ $comment->created_at->format('d M H:i') }} @if($comment->is_internal) · internal @endif</div>
                    <div class="whitespace-pre-wrap">{{ $comment->body }}</div>
                </div>
            @endforeach
            <form method="POST" action="{{ route('tickets.comment', $ticket) }}" class="space-y-2">
                @csrf
                <textarea class="field" name="body" rows="3" required placeholder="Write an update"></textarea>
                @if (auth()->user()->isStaff())<label class="text-sm"><input type="checkbox" name="is_internal" value="1"> Internal note (hidden from the customer)</label>@endif
                <button class="btn btn-primary" type="submit">Add comment</button>
            </form>
        </section>
    </div>

    <section class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="mb-2 font-semibold">Files</h2>
        <ul class="mb-3 text-sm">
            @foreach ($ticket->attachments as $file)
                @if (! $file->comment?->is_internal || auth()->user()->isStaff())
                    <li><a class="text-oc-blue" href="{{ route('tickets.attachments.download', [$ticket, $file]) }}">{{ $file->original_name }}</a></li>
                @endif
            @endforeach
        </ul>
        <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2">
            @csrf
            <input type="file" name="file" required>
            <button class="btn btn-ghost" type="submit">Upload</button>
        </form>
    </section>

    @if (auth()->user()->isStaff())
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('tickets.time', $ticket) }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
                @csrf
                <h2 class="font-semibold">Time, labour, call-out, travel</h2>
                <input class="field" type="date" name="work_date" value="{{ now()->toDateString() }}" required>
                <select class="field" name="kind">
                    <option value="labour">Time / labour</option>
                    <option value="call_out">Call-out</option>
                    <option value="travel">Travel</option>
                </select>
                <input class="field" name="minutes" type="number" min="0" placeholder="Minutes (labour)">
                <input class="field" name="quantity" inputmode="decimal" placeholder="Kilometres (travel)">
                <input class="field" name="description" placeholder="What was done" required>
                <input class="field" name="hourly_rate" placeholder="Rate in rands, blank for the default">
                <input type="hidden" name="vat_code" value="standard">
                <label class="block text-sm"><input type="checkbox" name="after_hours" value="1"> After hours (labour is multiplied when billed)</label>
                <label class="block text-sm"><input type="checkbox" name="billable" value="1" checked> Billable</label>
                <button class="btn btn-ghost" type="submit">Log time</button>
                <ul class="text-sm">@foreach ($ticket->timeEntries as $entry)<li>{{ $entry->kind?->label() ?? 'Labour' }} · {{ $entry->minutes }} min · {{ $entry->description }} @if($entry->invoice_line_id) · billed @endif</li>@endforeach</ul>
            </form>
            <form method="POST" action="{{ route('tickets.parts', $ticket) }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
                @csrf
                <h2 class="font-semibold">Parts</h2>
                <input class="field" name="description" placeholder="Part" required>
                <input class="field" name="quantity" value="1" required>
                <input class="field" name="unit_price" placeholder="Unit price in rands">
                <input type="hidden" name="vat_code" value="standard">
                <label class="text-sm"><input type="checkbox" name="billable" value="1" checked> Billable</label>
                <button class="btn btn-ghost" type="submit">Add part</button>
                <ul class="text-sm">@foreach ($ticket->parts as $part)<li>{{ $part->description }} · {{ $part->quantity }} @if($part->invoice_line_id) · billed @endif</li>@endforeach</ul>
            </form>
        </div>
        @can('bill', $ticket)
            <a class="btn btn-primary mt-4 inline-flex" href="{{ route('tickets.bill', $ticket) }}">Bill unbilled work</a>
        @endcan
    @endif
@endsection
