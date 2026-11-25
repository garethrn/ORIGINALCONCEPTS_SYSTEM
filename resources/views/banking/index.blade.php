@extends('layouts.app')
@section('content')
    <div class="mb-4 flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold text-oc-navy">Banking</h1>
        <a class="btn btn-primary" href="{{ route('banking.review') }}">For review</a>
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($accounts as $account)
            <a class="rounded-2xl bg-white p-4 shadow-sm" href="{{ route('banking.show', $account) }}">
                <div class="font-semibold">{{ $account->name }}</div>
                <div class="text-sm text-slate-500">{{ $account->account?->code }} {{ $account->institution }}</div>
            </a>
        @endforeach
    </div>
@endsection
