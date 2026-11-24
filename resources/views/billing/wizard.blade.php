@extends('layouts.app')
@section('content')
    <div class="mb-1 text-sm text-slate-500">{{ $client->displayName() }}@if ($ticket) · {{ $ticket->reference() }}@endif</div>
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Bill unbilled work</h1>
    @if ($rows === [])
        <div class="rounded-2xl bg-white p-4 shadow-sm">Nothing on this {{ $ticket ? 'ticket' : 'customer' }} is waiting to be billed.</div>
    @else
        <form method="POST" action="{{ $action }}" class="space-y-3">
            @csrf
            @foreach ($rows as $index => $row)
                <fieldset class="grid gap-2 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-12">
                    <input type="hidden" name="items[{{ $index }}][type]" value="{{ $row['type'] }}">
                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $row['id'] }}">
                    @if (! empty($row['product_id']))
                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $row['product_id'] }}">
                    @endif
                    <label class="flex items-center gap-2 text-sm md:col-span-12"><input type="checkbox" name="items[{{ $index }}][include]" value="1" checked> {{ $row['meta'] }}</label>
                    <input class="field md:col-span-5" name="items[{{ $index }}][description]" value="{{ $row['description'] }}" required>
                    <input class="field md:col-span-2" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" inputmode="decimal" required>
                    <input class="field md:col-span-2" name="items[{{ $index }}][unit_price]" value="{{ $row['unit_price'] }}" inputmode="decimal" required>
                    <select class="field md:col-span-3" name="items[{{ $index }}][vat_code]">
                        @foreach (['standard' => 'Standard VAT', 'zero' => 'Zero-rated', 'exempt' => 'Exempt'] as $value => $label)
                            <option value="{{ $value }}" @selected(($row['vat_code'] ?? 'standard') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="items[{{ $index }}][includes_vat]" value="0">
                    <label class="flex items-center gap-2 text-sm md:col-span-12"><input type="checkbox" name="items[{{ $index }}][includes_vat]" value="1" @checked($row['includes_vat'] ?? false)> Price includes VAT</label>
                </fieldset>
            @endforeach
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <label class="mb-1 block text-sm font-medium" for="group">Group invoice lines</label>
                <select class="field max-w-md" id="group" name="group">
                    <option value="ticket">By ticket</option>
                    <option value="type">By type</option>
                </select>
                <p class="mt-2 text-sm text-slate-500">Labour on the same ticket and rate becomes one line when grouping by ticket. Grouping by type does that across the customer's tickets. Parts stay on their own lines. Call-outs and travel are billed as entered and do not use the labour minimum.</p>
            </div>
            <button class="btn btn-primary" type="submit">Create draft invoice</button>
        </form>
    @endif
@endsection
