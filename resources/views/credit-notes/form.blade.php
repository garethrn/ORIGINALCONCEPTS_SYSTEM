@extends('layouts.app')
@php
    $lines = old('lines', $invoice->lines->map(fn ($line) => [
        'description' => $line->description,
        'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.') ?: '0',
        'unit_price' => $line->unitPriceRands(),
        'includes_vat' => (bool) $line->price_includes_vat,
        'vat_code' => $line->vat_code->value,
        'product_id' => $line->product_id,
    ])->all());
@endphp
@section('content')
    <h1 class="mb-2 text-2xl font-semibold text-oc-navy">Credit note for {{ $invoice->number }}</h1>
    <p class="mb-4 text-sm text-slate-600">This credit note will refer to tax invoice {{ $invoice->number }}.</p>
    <form method="POST" action="{{ route('credit-notes.store', $invoice) }}" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        <textarea class="field" name="notes" rows="2" placeholder="Reason">{{ old('notes') }}</textarea>
        <div class="space-y-3" data-lines>
            @foreach ($lines as $index => $line)
                @include('documents.line', ['index' => $index, 'line' => $line])
            @endforeach
        </div>
        <button class="btn btn-primary" type="submit">Save draft credit note</button>
    </form>
@endsection
