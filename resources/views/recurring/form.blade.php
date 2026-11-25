@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $schedule->exists ? 'Edit recurring invoice' : 'New recurring invoice' }}</h1>
    <form method="POST" action="{{ $schedule->exists ? route('recurring.update', $schedule) : route('recurring.store') }}" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        @if ($schedule->exists) @method('PUT') @endif
        <select class="field" name="client_id" required>
            <option value="">Customer</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(old('client_id', $schedule->client_id) == $client->id)>{{ $client->displayName() }}</option>
            @endforeach
        </select>
        <input class="field" name="name" value="{{ old('name', $schedule->name) }}" placeholder="Name, for example Monthly support" required>
        <div class="grid gap-2 md:grid-cols-3">
            <select class="field" name="frequency">
                @foreach (App\Enums\RecurringFrequency::cases() as $frequency)
                    <option value="{{ $frequency->value }}" @selected(old('frequency', $schedule->frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
                @endforeach
            </select>
            <input class="field" type="date" name="next_on" value="{{ old('next_on', optional($schedule->next_on)->toDateString()) }}" required>
            <input class="field" type="date" name="ends_on" value="{{ old('ends_on', optional($schedule->ends_on)->toDateString()) }}" placeholder="Ends">
        </div>
        <textarea class="field" name="notes" rows="2" placeholder="Notes copied onto each invoice">{{ old('notes', $schedule->notes) }}</textarea>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_issue" value="1" @checked(old('auto_issue', $schedule->auto_issue))> Issue the invoice automatically. Leave off to create a draft.</label>
        <input type="hidden" name="active" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $schedule->active ?? true))> Active</label>
        <div class="space-y-3">
            @foreach ($lines as $index => $line)
                @include('documents.line', ['index' => $index, 'line' => $line, 'kind' => 'recurring'])
            @endforeach
        </div>
        <button class="btn btn-primary" type="submit">Save schedule</button>
    </form>
@endsection
