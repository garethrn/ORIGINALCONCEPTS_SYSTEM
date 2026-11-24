@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">Catalogue</h1>@can('create', App\Models\Product::class)<a class="btn btn-gold" href="{{ route('products.create') }}">Add product</a>@endcan</div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">SKU</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Price</th><th></th></tr></thead>
            <tbody>
            @foreach ($products as $product)
                <tr class="border-t"><td class="px-4 py-3">{{ $product->sku }}</td><td class="px-4 py-3">{{ $product->name }}</td><td class="px-4 py-3">{{ $product->type->label() }}</td><td class="px-4 py-3">{{ \App\Support\Money::format($product->unit_price_cents) }} {{ $product->price_includes_vat ? 'incl' : 'excl' }}</td><td class="px-4 py-3">@can('update', $product)<a href="{{ route('products.edit', $product) }}">Edit</a>@endcan</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
@endsection
