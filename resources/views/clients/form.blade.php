@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $client->exists ? 'Edit customer' : 'New customer' }}</h1>
    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf
        @if ($client->exists) @method('PUT') @endif
        <input class="field" name="name" value="{{ old('name', $client->name) }}" placeholder="Legal name" required>
        <input class="field" name="trading_name" value="{{ old('trading_name', $client->trading_name) }}" placeholder="Trading name">
        <input class="field" name="email" type="email" value="{{ old('email', $client->email) }}" placeholder="Email">
        <input class="field" name="phone" value="{{ old('phone', $client->phone) }}" placeholder="Phone">
        <input class="field" name="vat_number" value="{{ old('vat_number', $client->vat_number) }}" placeholder="VAT number">
        <input class="field" name="registration_number" value="{{ old('registration_number', $client->registration_number) }}" placeholder="Registration number">
        <select class="field" name="payment_terms_days">
            @foreach ([0, 7, 14, 30, 60] as $days)<option value="{{ $days }}" @selected(old('payment_terms_days', $client->payment_terms_days) == $days)>{{ $days }} day terms</option>@endforeach
        </select>
        <input class="field" name="address_line1" value="{{ old('address_line1', $client->address_line1) }}" placeholder="Address">
        <input class="field" name="address_line2" value="{{ old('address_line2', $client->address_line2) }}" placeholder="Address line 2">
        <input class="field" name="city" value="{{ old('city', $client->city) }}" placeholder="City">
        <input class="field" name="province" value="{{ old('province', $client->province) }}" placeholder="Province">
        <input class="field" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" placeholder="Postal code">
        <input class="field" name="country" value="{{ old('country', $client->country ?: 'South Africa') }}" placeholder="Country">
        <textarea class="field md:col-span-2" name="notes" rows="3" placeholder="Notes">{{ old('notes', $client->notes) }}</textarea>
        <input class="field" name="labour_rate" value="{{ old('labour_rate', $client->labour_rate_cents !== null ? \App\Support\Money::centsToRands((int) $client->labour_rate_cents) : '') }}" placeholder="Labour rate override, blank for company rate">
        <input class="field" name="call_out_fee" value="{{ old('call_out_fee', $client->call_out_fee_cents !== null ? \App\Support\Money::centsToRands((int) $client->call_out_fee_cents) : '') }}" placeholder="Call-out override, blank for company fee">
        <input class="field" name="travel_rate" value="{{ old('travel_rate', $client->travel_rate_cents !== null ? \App\Support\Money::centsToRands((int) $client->travel_rate_cents) : '') }}" placeholder="Travel per km override, blank for company rate">
        <input type="hidden" name="is_active" value="0">
        <label class="text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active))> Active</label>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save customer</button></div>
    </form>
@endsection
