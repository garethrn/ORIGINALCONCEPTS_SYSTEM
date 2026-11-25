@extends('layouts.app')
@php
    $optionRows = [];
    if ($kind === 'quote') {
        $optionRows = old('options');
        if (! is_array($optionRows)) {
            $optionRows = $document->exists ? $document->options->map(fn ($option) => ['name' => $option->name])->all() : [];
        }
        $optionRows = array_pad($optionRows, 3, ['name' => '']);
    }
    $lines = old('lines');
    if (! is_array($lines)) {
        $lines = $document->exists
            ? $document->lines->map(function ($line) use ($kind, $document) {
                $row = [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.') ?: '0',
                    'unit_price' => $line->unitPriceRands(),
                    'includes_vat' => (bool) $line->price_includes_vat,
                    'vat_code' => $line->vat_code->value,
                    'product_id' => $line->product_id,
                ];
                if ($kind === 'quote') {
                    $row['placement'] = $line->is_optional ? 'optional' : 'included';
                    if ($line->quote_option_id) {
                        $index = $document->options->search(fn ($option) => (int) $option->id === (int) $line->quote_option_id);
                        $row['placement'] = $index === false ? 'included' : 'option-'.$index;
                    }
                }

                return $row;
            })->all()
            : [['description' => '', 'quantity' => '1', 'unit_price' => '0.00', 'includes_vat' => false, 'vat_code' => 'standard', 'product_id' => null, 'placement' => 'included']];
    }
@endphp
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $document->exists ? 'Edit' : 'New' }} {{ $kind }}</h1>
    <form method="POST" action="{{ $action }}" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
        <div>
            <label class="mb-1 block text-sm font-medium">Customer</label>
            <select class="field" name="client_id" required>
                <option value="">Select</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $document->client_id) == $client->id)>{{ $client->displayName() }}</option>
                @endforeach
            </select>
        </div>
        @if ($kind === 'quote')
            <div>
                <label class="mb-1 block text-sm font-medium">Valid until</label>
                <input class="field" type="date" name="valid_until" value="{{ old('valid_until', optional($document->valid_until)->toDateString()) }}">
            </div>
            <div class="grid gap-2 md:grid-cols-3">
                <div class="md:col-span-3 text-sm font-medium">Options</div>
                @foreach ($optionRows as $index => $option)
                    <input class="field" name="options[{{ $index }}][name]" value="{{ $option['name'] ?? '' }}" placeholder="Option {{ $index + 1 }}, leave blank if unused">
                @endforeach
                <p class="md:col-span-3 text-sm text-slate-500">Name two or more options when the customer must pick one. Lines can sit in every option, in one option, or as an optional add-on.</p>
            </div>
        @endif
        <div>
            <label class="mb-1 block text-sm font-medium">Notes</label>
            <textarea class="field" name="notes" rows="3">{{ old('notes', $document->notes) }}</textarea>
        </div>
        <div class="space-y-3" data-lines>
            @foreach ($lines as $index => $line)
                @include('documents.line', ['index' => $index, 'line' => $line, 'kind' => $kind, 'optionRows' => $optionRows])
            @endforeach
        </div>
        <button class="btn btn-ghost" type="button" data-add-line>Add line</button>
        <div><button class="btn btn-primary" type="submit">Save draft</button></div>
    </form>
    <template id="line-template">
        @include('documents.line', ['index' => '__INDEX__', 'line' => ['description' => '', 'quantity' => '1', 'unit_price' => '0.00', 'includes_vat' => false, 'vat_code' => 'standard', 'placement' => 'included'], 'kind' => $kind, 'optionRows' => $optionRows])
    </template>
@endsection
