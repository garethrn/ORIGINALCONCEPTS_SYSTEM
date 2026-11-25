@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Company settings</h1>
        <a class="btn btn-ghost" href="{{ route('bank-feeds.edit') }}">Bank feeds by email</a>
    </div>
    <form method="POST" action="{{ route('settings.update') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf @method('PUT')
        @foreach (['legal_name' => 'Legal name', 'trading_name' => 'Trading name', 'address_line1' => 'Address', 'address_line2' => 'Address line 2', 'city' => 'City', 'province' => 'Province', 'postal_code' => 'Postal code', 'country' => 'Country', 'vat_number' => 'VAT number', 'registration_number' => 'Registration number', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website', 'bbbee' => 'B-BBEE level', 'bank_name' => 'Bank', 'bank_account' => 'Account number', 'branch_code' => 'Branch code'] as $key => $label)
            <input class="field" name="{{ $key }}" value="{{ old($key, $company[$key] ?? '') }}" placeholder="{{ $label }}" @if(in_array($key, ['country', 'email'])) required @endif>
        @endforeach
        <textarea class="field md:col-span-2" name="invoice_footer" rows="2">{{ old('invoice_footer', $company['invoice_footer'] ?? '') }}</textarea>
        <h2 class="md:col-span-2 mt-2 font-semibold">Billing defaults</h2>
        <label class="text-sm">Standard labour rate, excl VAT
            <input class="field" name="default_labour_rate" value="{{ old('default_labour_rate', \App\Support\Money::centsToRands((int) ($company['default_labour_rate_cents'] ?? 65000))) }}" required>
        </label>
        <label class="text-sm">After-hours multiplier
            <input class="field" name="after_hours_multiplier" value="{{ old('after_hours_multiplier', $company['after_hours_multiplier'] ?? '1.5') }}" required>
        </label>
        <label class="text-sm">Call-out fee, excl VAT
            <input class="field" name="call_out_fee" value="{{ old('call_out_fee', \App\Support\Money::centsToRands((int) ($company['call_out_fee_cents'] ?? 45000))) }}" required>
        </label>
        <label class="text-sm">Travel per km, excl VAT
            <input class="field" name="travel_rate" value="{{ old('travel_rate', \App\Support\Money::centsToRands((int) ($company['travel_rate_cents'] ?? 650))) }}" required>
        </label>
        <label class="text-sm">Minimum billable minutes
            <input class="field" name="minimum_billable_minutes" type="number" min="0" max="480" value="{{ old('minimum_billable_minutes', $company['minimum_billable_minutes'] ?? 30) }}" required>
        </label>
        <label class="text-sm">Round labour up to the next
            <select class="field" name="rounding_minutes">
                @foreach ([1, 5, 15, 30, 60] as $step)
                    <option value="{{ $step }}" @selected(old('rounding_minutes', $company['rounding_minutes'] ?? 15) == $step)>{{ $step }} minutes</option>
                @endforeach
            </select>
        </label>
        @php $vatPercent = rtrim(rtrim(number_format(((int) ($company['standard_vat_basis_points'] ?? 1500)) / 100, 2, '.', ''), '0'), '.'); @endphp
        <label class="text-sm">Standard VAT percent
            <input class="field" name="standard_vat_percent" value="{{ old('standard_vat_percent', $vatPercent) }}" required>
        </label>
        <label class="text-sm">VAT basis
            <select class="field" name="vat_basis">
                <option value="invoice" @selected(old('vat_basis', $company['vat_basis'] ?? 'invoice') === 'invoice')>Invoice basis</option>
                <option value="cash" @selected(old('vat_basis', $company['vat_basis'] ?? 'invoice') === 'cash')>Cash basis</option>
            </select>
        </label>
        <label class="text-sm">Financial year starts
            <select class="field" name="fiscal_year_start_month">
                @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $index => $month)
                    <option value="{{ $index + 1 }}" @selected((int) old('fiscal_year_start_month', $company['fiscal_year_start_month'] ?? 3) === $index + 1)>{{ $month }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Time on invoices
            <select class="field" name="invoice_time_layout">
                <option value="summary" @selected(old('invoice_time_layout', $company['invoice_time_layout'] ?? 'summary') === 'summary')>Summary per ticket</option>
                <option value="detail" @selected(old('invoice_time_layout', $company['invoice_time_layout'] ?? 'summary') === 'detail')>Each time entry</option>
            </select>
        </label>
        <input type="hidden" name="show_prices_excl_vat" value="0">
        <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="show_prices_excl_vat" value="1" @checked(old('show_prices_excl_vat', $company['show_prices_excl_vat'] ?? '1') !== '0')> Show line values exclusive of VAT, with a VAT line</label>
        <p class="md:col-span-2 text-sm text-slate-500">Labour is rounded up, then the minimum is applied. Call-outs and travel do not use the minute minimum. A customer can override the labour, call-out, and travel rates.</p>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
    <section class="mt-4 rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Later phases</h2>
        <p class="mt-1">PayFast and Yoco are not connected. Payments with those methods are recorded by hand. Recurring subscriptions are not billed automatically. {{ $payfastConfigured ? '' : 'No live gateway calls are made.' }}</p>
    </section>
@endsection
