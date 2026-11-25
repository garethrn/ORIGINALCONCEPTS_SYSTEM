@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Suppliers</h1>
    <form method="POST" action="{{ route('suppliers.store') }}" class="mb-4 grid gap-2 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-5">
        @csrf
        <input class="field" name="name" placeholder="Supplier name" required>
        <input class="field" name="email" placeholder="Email">
        <input class="field" name="phone" placeholder="Phone">
        <input class="field" name="vat_number" placeholder="VAT number">
        <input type="hidden" name="payment_terms_days" value="30">
        <button class="btn btn-primary" type="submit">Add supplier</button>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            @foreach ($suppliers as $supplier)
                <tr class="border-t"><td class="px-4 py-3"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td><td class="px-4 py-3">{{ $supplier->vat_number }}</td></tr>
            @endforeach
        </table>
    </div>
@endsection
