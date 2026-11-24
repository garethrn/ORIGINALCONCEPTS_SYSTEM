@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Company settings</h1>
    <form method="POST" action="{{ route('settings.update') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf @method('PUT')
        @foreach (['legal_name' => 'Legal name', 'trading_name' => 'Trading name', 'address_line1' => 'Address', 'address_line2' => 'Address line 2', 'city' => 'City', 'province' => 'Province', 'postal_code' => 'Postal code', 'country' => 'Country', 'vat_number' => 'VAT number', 'registration_number' => 'Registration number', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website', 'bbbee' => 'B-BBEE level', 'bank_name' => 'Bank', 'bank_account' => 'Account number', 'branch_code' => 'Branch code'] as $key => $label)
            <input class="field" name="{{ $key }}" value="{{ old($key, $company[$key] ?? '') }}" placeholder="{{ $label }}" @if(in_array($key, ['country', 'email'])) required @endif>
        @endforeach
        <textarea class="field md:col-span-2" name="invoice_footer" rows="2">{{ old('invoice_footer', $company['invoice_footer'] ?? '') }}</textarea>
        <input class="field" name="default_labour_rate" value="{{ old('default_labour_rate', \App\Support\Money::centsToRands((int) ($company['default_labour_rate_cents'] ?? 65000))) }}" placeholder="Default labour rate (rands)" required>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
    <section class="mt-4 rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Later phases</h2>
        <p class="mt-1">PayFast and Yoco are not connected. Payments with those methods are recorded by hand. Recurring subscriptions are not billed automatically. {{ $payfastConfigured ? '' : 'No live gateway calls are made.' }}</p>
    </section>
@endsection
