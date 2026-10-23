<?php

namespace App\Providers;

use App\WhatsApp\ProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * @2026-Alifiandra
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        RateLimiter::for('wa-api', function (Request $request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute(max(1, (int) config('whatsapp.api_rate_limit', 60)))
                ->by($key ? 'key:'.$key->id : 'ip:'.$request->ip());
        });
    }
}
