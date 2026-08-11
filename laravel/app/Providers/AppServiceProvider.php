<?php

namespace App\Providers;

use App\Contracts\BillingGateway;
use App\Services\Billing\PendingBillingGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingGateway::class, PendingBillingGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('waflow-registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('waflow-api', function (Request $request) {
            $key = $request->attributes->get('waflow.api_key');

            return Limit::perMinute((int) config('waflow.api.requests_per_minute', 120))
                ->by($key?->id ? 'key:'.$key->id : 'ip:'.$request->ip());
        });
    }
}
