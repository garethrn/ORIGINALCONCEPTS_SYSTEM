@extends('layouts.guest')
@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <p class="text-sm text-slate-600">Enter your email. If an account exists, we will send a reset link.</p>
        <input class="field" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">
        <button class="btn btn-primary w-full" type="submit">Send reset link</button>
        <a class="block text-center text-sm" href="{{ route('login') }}">Back to sign in</a>
    </form>
@endsection
