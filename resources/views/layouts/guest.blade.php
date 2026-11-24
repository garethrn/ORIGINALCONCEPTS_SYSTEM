<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Sign in' }} — Original Concepts Support</title>
    @include('partials.pwa')
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-8">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-oc-gold text-xl font-bold text-oc-navy">OC</div>
            <h1 class="text-2xl font-semibold text-oc-navy">Original Concepts</h1>
            <p class="text-sm text-slate-600">Support, tickets, and billing</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</body>
</html>
