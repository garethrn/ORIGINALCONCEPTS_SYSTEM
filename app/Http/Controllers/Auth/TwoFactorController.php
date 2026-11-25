<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.pending_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = User::query()->find($request->session()->get('login.pending_id'));
        if (! $user || ! $user->is_active) {
            return redirect()->route('login');
        }

        $code = (string) $request->input('code');
        $valid = $user->totp_secret && $totp->verify($user->totp_secret, $code);
        if (! $valid) {
            $valid = $this->consumeRecoveryCode($user, $code);
        }
        if (! $valid) {
            $audit->log('auth.two_factor_failed', $user, null, null, $user->id);

            return back()->withErrors(['code' => 'That code was not accepted.']);
        }

        $request->session()->forget('login.pending_id');
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', $user, null, ['two_factor' => true], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function start(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);
        $secret = $totp->generateSecret();
        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => null,
        ])->save();

        return back()->with('totp_setup', [
            'secret' => $secret,
            'uri' => $totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function confirm(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);
        $data = $request->validate(['code' => ['required', 'string']]);
        if (! $user->totp_secret || ! $totp->verify($user->totp_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code did not match. Start setup again.']);
        }

        $plain = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(10));
            $plain[] = $code;
            $hashes[] = Hash::make($code);
        }
        $user->forceFill([
            'totp_confirmed_at' => now(),
            'recovery_codes' => $hashes,
        ])->save();

        return back()->with('recovery_codes', $plain)->with('status', 'Two-factor authentication is on. Store the recovery codes.');
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);
        $data = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string'],
        ]);
        if (! $user->totp_secret || ! $totp->verify($user->totp_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code was not accepted.']);
        }
        $user->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'recovery_codes' => null,
        ])->save();

        return back()->with('status', 'Two-factor authentication is off.');
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        foreach ($user->recovery_codes ?? [] as $index => $hash) {
            if (Hash::check(Str::upper(trim($code)), $hash)) {
                $remaining = $user->recovery_codes;
                unset($remaining[$index]);
                $user->recovery_codes = array_values($remaining);
                $user->save();

                return true;
            }
        }

        return false;
    }
}
