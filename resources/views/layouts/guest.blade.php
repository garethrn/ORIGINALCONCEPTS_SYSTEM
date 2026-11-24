<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Sign in' }} — Original Concepts Support</title>
    @include('partials.pwa')
</head>
<body class="antialiased">
    <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-8">
        <div class="mb-6 text-center">
            <img src="{{ asset('brand/logo.png') }}" alt="Original Concepts" class="oc-logo-login">
            <h1 class="mt-4 text-2xl">Original Concepts Support</h1>
            <p class="text-sm">Support, tickets, and billing</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</body>
</html>
