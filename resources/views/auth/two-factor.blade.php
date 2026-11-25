@extends('layouts.guest')
@section('content')
    <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
        @csrf
        <p class="text-sm text-slate-600">Enter the 6-digit code from your authenticator, or a recovery code.</p>
        <input class="field" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
        <button class="btn btn-primary w-full" type="submit">Continue</button>
    </form>
@endsection
