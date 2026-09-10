<?php

namespace App\Providers;

use App\Contracts\BillingGateway;
use App\Services\Billing\InterBillingGateway;
use App\Services\Billing\PendingBillingGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingGateway::class, fn ($app) => match (config('billing.driver')) {
            'inter' => $app->make(InterBillingGateway::class),
            default => $app->make(PendingBillingGateway::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        RateLimiter::for('waflow-registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('waflow-password-email', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('waflow-password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('waflow-api-auth', function (Request $request): array {
            $credential = $request->bearerToken() ?: $request->header('X-Waflow-Key') ?: 'missing';

            return [
                Limit::perMinute((int) config('waflow.api.authentication_attempts_per_ip', 300))
                    ->by('api-auth-ip:'.$request->ip()),
                Limit::perMinute((int) config('waflow.api.authentication_attempts_per_credential', 120))
                    ->by('api-auth-credential:'.hash('sha256', (string) $credential)),
            ];
        });
        RateLimiter::for('waflow-api', function (Request $request) {
            $key = $request->attributes->get('waflow.api_key');

            return Limit::perMinute((int) config('waflow.api.requests_per_minute', 120))
                ->by($key?->id ? 'key:'.$key->id : 'ip:'.$request->ip());
        });
        RateLimiter::for('waflow-message-writes', fn (Request $request) => Limit::perMinute((int) config('waflow.api.message_writes_per_minute', 60))
            ->by('messages:key:'.$request->attributes->get('waflow.api_key')?->id));
        RateLimiter::for('waflow-bulk-messages', fn (Request $request) => Limit::perMinute((int) config('waflow.api.bulk_requests_per_minute', 10))
            ->by('bulk:key:'.$request->attributes->get('waflow.api_key')?->id));
        RateLimiter::for('waflow-sensitive-api', fn (Request $request) => Limit::perMinute((int) config('waflow.api.sensitive_writes_per_minute', 20))
            ->by('sensitive:key:'.$request->attributes->get('waflow.api_key')?->id));
        RateLimiter::for('waflow-openwa-webhook', fn (Request $request) => Limit::perMinute((int) config('waflow.webhooks.openwa_requests_per_minute', 300))
            ->by('openwa-webhook:'.$request->ip()));
        RateLimiter::for('waflow-inter-webhook', fn (Request $request) => Limit::perMinute((int) config('waflow.webhooks.inter_requests_per_minute', 60))
            ->by('inter-webhook:'.$request->ip()));
    }
}
