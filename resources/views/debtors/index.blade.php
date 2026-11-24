@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Debtors</h1>
    @forelse ($rows as $invoices)
        @php $client = $invoices->first()->client; $total = $invoices->sum(fn ($invoice) => $invoice->balanceCents()); @endphp
        <section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex justify-between"><h2 class="font-semibold">{{ $client?->displayName() }}</h2><div>{{ \App\Support\Money::format($total) }}</div></div>
            <ul class="mt-2 text-sm">
                @foreach ($invoices as $invoice)
                    <li><a class="text-oc-blue" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a> due {{ optional($invoice->due_date)->format('d M Y') }} · {{ \App\Support\Money::format($invoice->balanceCents()) }} @if($invoice->isOverdue()) overdue @endif</li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>No outstanding invoices.</p>
    @endforelse
@endsection
