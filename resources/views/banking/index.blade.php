@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Banking</h1>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($accounts as $account)
            <a class="rounded-2xl bg-white p-4 shadow-sm" href="{{ route('banking.show', $account) }}">
                <div class="font-semibold">{{ $account->name }}</div>
                <div class="text-sm text-slate-500">{{ $account->account?->code }} {{ $account->institution }}</div>
            </a>
        @endforeach
    </div>
@endsection
