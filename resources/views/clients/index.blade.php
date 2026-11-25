@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">Customers</h1>@can('create', App\Models\Client::class)<a class="btn btn-gold" href="{{ route('clients.create') }}">Add customer</a>@endcan</div>
    <form class="mb-4" method="GET"><input class="field" name="q" value="{{ request('q') }}" placeholder="Search"></form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">VAT</th><th class="px-4 py-2">Terms</th></tr></thead>
            <tbody>
            @foreach ($clients as $client)
                <tr class="border-t"><td class="px-4 py-3"><a class="text-oc-blue" href="{{ route('clients.show', $client) }}">{{ $client->displayName() }}</a></td><td class="px-4 py-3">{{ $client->vat_number ?: '—' }}</td><td class="px-4 py-3">{{ $client->payment_terms_days }} days</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>
@endsection
