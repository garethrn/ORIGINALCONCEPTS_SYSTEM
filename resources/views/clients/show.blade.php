@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-oc-navy">{{ $client->displayName() }}</h1>
            <div class="text-sm text-slate-600">{{ $client->name }} · VAT {{ $client->vat_number ?: '—' }} · {{ $client->payment_terms_days }} day terms</div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('create', App\Models\Invoice::class)<a class="btn btn-primary" href="{{ route('clients.bill', $client) }}">Bill unbilled work</a>@endcan
            @can('view', $client)<a class="btn btn-ghost" href="{{ route('clients.statement', $client) }}">Statement</a>@endcan
            @can('update', $client)<a class="btn btn-ghost" href="{{ route('clients.edit', $client) }}">Edit</a>@endcan
        </div>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-2 font-semibold">Billing address</h2>
            @foreach ($client->addressLines() as $line)<div>{{ $line }}</div>@endforeach
            <div class="mt-2 text-sm">{{ $client->email }} {{ $client->phone }}</div>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-2 font-semibold">Contacts</h2>
            <ul class="mb-3 text-sm">@foreach ($client->contacts as $contact)<li class="flex justify-between gap-2"><span>{{ $contact->name }} · {{ $contact->email }}</span>@can('update', $client)<form method="POST" action="{{ route('clients.contacts.destroy', [$client, $contact]) }}">@csrf @method('DELETE')<button class="text-red-700" type="submit">Remove</button></form>@endcan</li>@endforeach</ul>
            @can('update', $client)
                <form method="POST" action="{{ route('clients.contacts.store', $client) }}" class="grid gap-2">@csrf<input class="field" name="name" placeholder="Name" required><input class="field" name="email" placeholder="Email"><input class="field" name="phone" placeholder="Phone"><button class="btn btn-ghost" type="submit">Add contact</button></form>
            @endcan
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm lg:col-span-2">
            <h2 class="mb-2 font-semibold">Sites</h2>
            <ul class="mb-3 text-sm">
                @foreach ($client->sites as $site)
                    <li class="mb-2">{{ $site->name }} {{ $site->city }}
                        @if ($site->directionsUrl())<a class="text-oc-blue" href="{{ $site->directionsUrl() }}">Directions</a>@endif
                        @if ($site->latitude) · {{ $site->latitude }}, {{ $site->longitude }} @endif
                    </li>
                @endforeach
            </ul>
            @can('update', $client)
                <form method="POST" action="{{ route('clients.sites.store', $client) }}" class="grid gap-2 md:grid-cols-2">@csrf<input class="field" name="name" placeholder="Site name" required><input class="field" name="city" placeholder="City"><input class="field" name="address_line1" placeholder="Address"><input class="field" name="latitude" placeholder="Latitude"><input class="field" name="longitude" placeholder="Longitude"><button class="btn btn-ghost" type="submit">Add site</button></form>
            @endcan
        </section>
    </div>
@endsection
