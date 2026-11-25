@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">{{ $userModel->exists ? 'Edit person' : 'New person' }}</h1>
    <form method="POST" action="{{ $userModel->exists ? route('users.update', $userModel) : route('users.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf @if($userModel->exists) @method('PUT') @endif
        <input class="field" name="name" value="{{ old('name', $userModel->name) }}" placeholder="Name" required>
        <input class="field" name="email" type="email" value="{{ old('email', $userModel->email) }}" placeholder="Email" required>
        <input class="field" name="phone" value="{{ old('phone', $userModel->phone) }}" placeholder="Phone">
        <select class="field" name="role">@foreach (\App\Enums\Role::cases() as $role)<option value="{{ $role->value }}" @selected(old('role', $userModel->role?->value) === $role->value)>{{ $role->label() }}</option>@endforeach</select>
        <select class="field" name="client_id"><option value="">Customer login link</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $userModel->client_id) == $client->id)>{{ $client->displayName() }}</option>@endforeach</select>
        <input class="field" name="password" type="password" placeholder="{{ $userModel->exists ? 'New password (optional)' : 'Password' }}" autocomplete="new-password">
        <input class="field" name="password_confirmation" type="password" placeholder="Confirm password" autocomplete="new-password">
        <input type="hidden" name="is_active" value="0"><label class="text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $userModel->is_active ?? true))> Active</label>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
@endsection
