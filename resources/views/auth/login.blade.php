@extends('layouts.guest')
@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium" for="email">Email</label>
            <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium" for="password">Password</label>
            <input class="field" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
        <a class="block text-center text-sm text-oc-blue" href="{{ route('password.request') }}">Forgot password</a>
    </form>
@endsection
