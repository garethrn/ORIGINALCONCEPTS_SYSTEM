<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Dashboard' }} — Original Concepts Support</title>
    @include('partials.pwa')
</head>
<body class="antialiased">
@php
    $user = auth()->user();
    $links = [
        ['Dashboard', route('dashboard'), true],
        ['Tickets', route('tickets.index'), true],
        ['Customers', route('clients.index'), $user->isStaff()],
        ['Quotes', route('quotes.index'), $user->role->canBill() || $user->role->value === 'client'],
        ['Invoices', route('invoices.index'), $user->role->canBill() || $user->role->value === 'client'],
        ['Payments', route('payments.index'), $user->role->canTakePayment()],
        ['Debtors', route('debtors.index'), $user->role->canBill()],
        ['Catalogue', route('products.index'), $user->isStaff()],
        ['Assistant', route('assistant.index'), $user->role->canUseAssistant()],
        ['People', route('users.index'), $user->role->canManageUsers()],
        ['Audit', route('audit.index'), $user->role->value === 'admin'],
        ['Settings', route('settings.edit'), $user->role->canManageSettings()],
    ];
@endphp
<header class="oc-header">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
        <button type="button" data-nav-toggle class="btn btn-ghost min-w-11 lg:hidden" aria-label="Open menu">Menu</button>
        <a href="{{ route('dashboard') }}"><img src="{{ asset('brand/logo.png') }}" alt="Original Concepts" class="oc-logo"></a>
        <div class="ml-auto text-sm">{{ $user->name }}</div>
    </div>
    <nav data-nav class="hidden border-t border-white/10 lg:block">
        <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-2 lg:flex-row lg:flex-wrap lg:items-center">
            @foreach ($links as [$label, $href, $show])
                @if ($show)
                    @php $path = trim((string) parse_url($href, PHP_URL_PATH), '/'); @endphp
                    <a class="rounded-lg px-3 py-2 text-sm {{ request()->is($path) || request()->is($path.'/*') ? 'is-active' : '' }}" href="{{ $href }}">{{ $label }}</a>
                @endif
            @endforeach
            <a class="rounded-lg px-3 py-2 text-sm hover:bg-white/10 lg:ml-auto" href="{{ route('profile.edit') }}">Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg px-3 py-2 text-sm hover:bg-white/10" type="submit">Sign out</button>
            </form>
        </div>
    </nav>
</header>
<main class="mx-auto max-w-6xl px-4 py-5">
    @include('partials.flash')
    @yield('content')
</main>
</body>
</html>
