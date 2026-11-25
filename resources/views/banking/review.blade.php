@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">For review</h1>
    @if ($notices->isNotEmpty())
        <div class="mb-4 rounded-2xl bg-white p-4 text-sm shadow-sm">
            @foreach ($notices as $notice)<div>{{ $notice->body }}</div>@endforeach
        </div>
    @endif
    <div class="space-y-4">
        @forelse ($transactions as $transaction)
            <section class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ \App\Support\Money::format($transaction->amount_cents) }} · {{ $transaction->posted_on->format('d M Y') }}</div>
                        <div class="text-sm text-slate-500">{{ $transaction->bankAccount?->name }} · {{ $transaction->description }}</div>
                    </div>
                    <x-status :value="$transaction->review_status === 'allocated' ? 'Paid' : 'Waiting'" />
                </div>
                @if ($transaction->suggestion)
                    <p class="mt-2 text-sm">Suggested: {{ $transaction->suggestion }} @if($transaction->confidence) · {{ $transaction->confidence }}% @endif</p>
                @endif
                @if ($transaction->review_status === 'pending')
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('banking.review.accept', $transaction) }}">@csrf<button class="btn btn-primary" type="submit">Accept</button></form>
                        <form method="POST" action="{{ route('banking.review.ignore', $transaction) }}">@csrf<button class="btn btn-ghost" type="submit">Ignore</button></form>
                    </div>
                    <form method="POST" action="{{ route('banking.review.split', $transaction) }}" class="mt-3 grid gap-2 md:grid-cols-2">
                        @csrf
                        <input class="field" name="lines[0][invoice_id]" placeholder="First invoice id">
                        <input class="field" name="lines[0][amount]" placeholder="Amount">
                        <input class="field" name="lines[1][invoice_id]" placeholder="Second invoice id">
                        <input class="field" name="lines[1][amount]" placeholder="Amount">
                        <button class="btn btn-ghost" type="submit">Split</button>
                    </form>
                    <form method="POST" action="{{ route('banking.review.expense', $transaction) }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <select class="field max-w-xs" name="account_id">
                            @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
                        </select>
                        <button class="btn btn-ghost" type="submit">Assign to expense</button>
                    </form>
                @elseif ($transaction->review_status === 'allocated')
                    <form class="mt-3" method="POST" action="{{ route('banking.review.undo', $transaction) }}">@csrf<button class="btn btn-ghost" type="submit">Undo allocation</button></form>
                @endif
            </section>
        @empty
            <p class="text-sm text-slate-500">Nothing from the bank feed is waiting.</p>
        @endforelse
    </div>
@endsection
