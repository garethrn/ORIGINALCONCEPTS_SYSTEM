<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Ai\AiDisabledException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'staff' => EnsureUserIsStaff::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AiDisabledException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The assistant is switched off until an AI API key is configured.'], 503);
            }

            return back()->with('error', 'The assistant is switched off until an AI API key is configured.');
        });
    })->create();
