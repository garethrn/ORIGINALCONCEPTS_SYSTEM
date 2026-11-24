<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        $user = User::query()->where('email', $credentials['email'])->first();
        if (! $user || ! $user->is_active || ! Auth::validate($credentials)) {
            RateLimiter::hit($key, 900);
            $audit->log('auth.failed', null, null, ['email' => $credentials['email']]);

            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        RateLimiter::clear($key);

        if ($user->hasConfirmedTwoFactor()) {
            $request->session()->put('login.pending_id', $user->id);
            $request->session()->regenerate();

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', $user, null, null, $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
