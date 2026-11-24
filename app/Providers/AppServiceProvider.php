<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Ai\ChatClient;
use App\Contracts\Payments\OnlinePaymentGateway;
use App\Contracts\Payments\RecurringBillingGateway;
use App\Services\Ai\FakeChatClient;
use App\Services\Ai\OpenAiCompatibleChatClient;
use App\Services\Payments\UnavailablePayFastGateway;
use App\Services\Payments\UnavailableSubscriptionGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChatClient::class, function () {
            return config('support.ai.driver') === 'fake'
                ? new FakeChatClient
                : new OpenAiCompatibleChatClient;
        });
        $this->app->bind(OnlinePaymentGateway::class, UnavailablePayFastGateway::class);
        $this->app->singleton(RecurringBillingGateway::class, UnavailableSubscriptionGateway::class);
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinutes(15, 5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('ai', function (Request $request) {
            $key = (string) ($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(10)->by('ai-minute:'.$key),
                Limit::perHour(60)->by('ai-hour:'.$key),
            ];
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        View::composer(['layouts.app', 'layouts.guest', 'pdf.document', 'pwa.offline'], function ($view) {
            $view->with('companyName', config('support.company.legal_name'));
        });
    }
}
