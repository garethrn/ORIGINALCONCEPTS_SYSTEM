@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $product->exists ? 'Edit product' : 'New product' }}</h1>
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf @if($product->exists) @method('PUT') @endif
        <input class="field" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="SKU" required>
        <input class="field" name="name" value="{{ old('name', $product->name) }}" placeholder="Name" required>
        <select class="field" name="type">@foreach (\App\Enums\ProductType::cases() as $type)<option value="{{ $type->value }}" @selected(old('type', $product->type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select>
        <input class="field" name="unit" value="{{ old('unit', $product->unit ?: 'each') }}" placeholder="Unit (hour, each, month)" required>
        <input class="field" name="unit_price" value="{{ old('unit_price', $product->exists ? \App\Support\Money::centsToRands($product->unit_price_cents) : '') }}" placeholder="Price in rands" required>
        <select class="field" name="vat_code">@foreach (\App\Enums\VatCode::cases() as $code)<option value="{{ $code->value }}" @selected(old('vat_code', $product->vat_code?->value ?: 'standard') === $code->value)>{{ $code->label() }}</option>@endforeach</select>
        <input type="hidden" name="price_includes_vat" value="0"><label class="text-sm"><input type="checkbox" name="price_includes_vat" value="1" @checked(old('price_includes_vat', $product->price_includes_vat))> Price includes VAT</label>
        <input type="hidden" name="is_active" value="0"><label class="text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Active</label>
        <input type="hidden" name="track_stock" value="0"><label class="text-sm"><input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))> Track stock</label>
        <input class="field" name="quantity_on_hand" value="{{ old('quantity_on_hand', $product->quantity_on_hand ?? '0') }}" placeholder="Quantity on hand">
        <textarea class="field md:col-span-2" name="description" rows="3">{{ old('description', $product->description) }}</textarea>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
@endsection
