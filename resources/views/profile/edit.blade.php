@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Profile</h1>
    <form method="POST" action="{{ route('profile.password') }}" class="mb-4 space-y-3 rounded-2xl bg-white p-4 shadow-sm">
        @csrf @method('PUT')
        <h2 class="font-semibold">Password</h2>
        <input class="field" type="password" name="current_password" placeholder="Current password" required>
        <input class="field" type="password" name="password" placeholder="New password" required>
        <input class="field" type="password" name="password_confirmation" placeholder="Confirm" required>
        <button class="btn btn-primary" type="submit">Update password</button>
    </form>
    @if (auth()->user()->isStaff())
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Two-factor authentication</h2>
            @if (session('totp_setup'))
                <p class="mt-2 text-sm">Add this secret to your authenticator app, then enter a code.</p>
                <p class="mt-2 break-all font-mono text-sm">{{ session('totp_setup.secret') }}</p>
                <p class="mt-2 break-all text-xs text-slate-500">{{ session('totp_setup.uri') }}</p>
                <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="mt-3 flex gap-2">@csrf<input class="field" name="code" inputmode="numeric" required><button class="btn btn-primary" type="submit">Confirm</button></form>
            @endif
            @if (session('recovery_codes'))
                <ul class="mt-3 font-mono text-sm">@foreach (session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul>
            @endif
            @if (auth()->user()->hasConfirmedTwoFactor())
                <p class="mt-2 text-sm">Two-factor authentication is on.</p>
                <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="mt-3 space-y-2">@csrf @method('DELETE')<input class="field" type="password" name="password" placeholder="Password" required><input class="field" name="code" placeholder="Authenticator code" required><button class="btn btn-danger" type="submit">Turn off</button></form>
            @else
                <form class="mt-3" method="POST" action="{{ route('profile.two-factor.start') }}">@csrf<button class="btn btn-ghost" type="submit">Set up authenticator</button></form>
            @endif
        </section>
    @endif
@endsection
