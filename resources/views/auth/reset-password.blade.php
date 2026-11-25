@extends('layouts.guest')
@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input class="field" name="email" type="email" value="{{ old('email', $email) }}" required>
        <input class="field" name="password" type="password" placeholder="New password" required autocomplete="new-password">
        <input class="field" name="password_confirmation" type="password" placeholder="Confirm password" required autocomplete="new-password">
        <button class="btn btn-primary w-full" type="submit">Update password</button>
    </form>
@endsection
