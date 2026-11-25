@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $account->exists ? 'Edit account' : 'New account' }}</h1>
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf
        @if ($account->exists) @method('PUT') @endif
        <input class="field" name="code" value="{{ old('code', $account->code) }}" placeholder="Code" @if($account->isSystem()) readonly @endif required>
        <input class="field" name="name" value="{{ old('name', $account->name) }}" placeholder="Name" required>
        <select class="field" name="type" @if($account->isSystem()) disabled @endif>
            @foreach (['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $account->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($account->isSystem())<input type="hidden" name="type" value="{{ $account->type }}">@endif
        <input type="hidden" name="active" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $account->active ?? true))> Active</label>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save account</button></div>
    </form>
@endsection
