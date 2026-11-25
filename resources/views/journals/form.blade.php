@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Manual journal</h1>
    <form method="POST" action="{{ route('journals.store') }}" class="space-y-3 rounded-2xl bg-white p-4 shadow-sm">
        @csrf
        <input class="field" type="date" name="entry_date" value="{{ old('entry_date', today()->toDateString()) }}" required>
        <input class="field" name="memo" value="{{ old('memo') }}" placeholder="Memo" required>
        @for ($i = 0; $i < 4; $i++)
            <div class="grid gap-2 md:grid-cols-4">
                <select class="field md:col-span-2" name="lines[{{ $i }}][account_id]">
                    <option value="">Account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
                <input class="field" name="lines[{{ $i }}][debit]" placeholder="Debit" inputmode="decimal">
                <input class="field" name="lines[{{ $i }}][credit]" placeholder="Credit" inputmode="decimal">
                <input class="field md:col-span-4" name="lines[{{ $i }}][description]" placeholder="Line description">
            </div>
        @endfor
        <button class="btn btn-primary" type="submit">Post journal</button>
    </form>
@endsection
