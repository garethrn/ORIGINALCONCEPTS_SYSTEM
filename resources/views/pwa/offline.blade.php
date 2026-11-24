<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Offline — Original Concepts Support</title>
    @include('partials.pwa')
</head>
<body>
    <main class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center px-6 text-center">
        <img src="{{ asset('brand/logo.png') }}" alt="Original Concepts" class="oc-logo-login">
        <h1 class="text-2xl font-semibold">You are offline</h1>
        <p class="mt-2 text-sm text-white/80">Original Concepts Support needs a connection to load tickets and invoices. Open the app again when you are back online.</p>
    </main>
</body>
</html>
