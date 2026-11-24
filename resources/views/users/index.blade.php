@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-semibold text-oc-navy">People</h1><a class="btn btn-gold" href="{{ route('users.create') }}">Add person</a></div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Customer</th><th></th></tr></thead>
        <tbody>@foreach ($users as $person)<tr class="border-t"><td class="px-4 py-3">{{ $person->name }}<div class="text-xs text-slate-500">{{ $person->email }}</div></td><td class="px-4 py-3">{{ $person->role->label() }}</td><td class="px-4 py-3">{{ $person->client?->displayName() }}</td><td class="px-4 py-3"><a href="{{ route('users.edit', $person) }}">Edit</a></td></tr>@endforeach</tbody></table></div>
@endsection
