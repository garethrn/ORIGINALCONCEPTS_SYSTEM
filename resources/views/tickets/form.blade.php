@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">New ticket</h1>
    <form method="POST" action="{{ route('tickets.store') }}" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        @if (auth()->user()->role->value === 'client')
            <p class="text-sm">This ticket will be logged for {{ auth()->user()->client?->displayName() }}.</p>
        @else
            <div><label class="mb-1 block text-sm font-medium">Customer</label>
                <select class="field" name="client_id" required>
                    <option value="">Select</option>
                    @foreach ($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->displayName() }}</option>@endforeach
                </select>
            </div>
            <div><label class="mb-1 block text-sm font-medium">Assign to</label>
                <select class="field" name="assigned_to"><option value="">Unassigned</option>@foreach ($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select>
            </div>
        @endif
        <input class="field" name="subject" value="{{ old('subject') }}" placeholder="Subject" required>
        <textarea class="field" name="description" rows="5" placeholder="What is going on?" required>{{ old('description') }}</textarea>
        <div class="grid gap-3 sm:grid-cols-2">
            <select class="field" name="category" required>
                @foreach (\App\Enums\TicketCategory::cases() as $category)<option value="{{ $category->value }}" @selected(old('category', 'it_support') === $category->value)>{{ $category->label() }}</option>@endforeach
            </select>
            <select class="field" name="priority" required>
                @foreach (\App\Enums\TicketPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>@endforeach
            </select>
        </div>
        <input class="field" name="location" value="{{ old('location') }}" placeholder="Service location (optional)">
        <button class="btn btn-primary" type="submit">Open ticket</button>
    </form>
@endsection
