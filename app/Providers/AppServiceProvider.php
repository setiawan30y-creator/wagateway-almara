<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\User;
use App\WhatsApp\ProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('wa-api', function (Request $request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute(max(1, (int) config('whatsapp.api_rate_limit', 60)))
                ->by($key ? 'key:'.$key->id : 'ip:'.$request->ip());
        });

        // Badge jumlah belum dibaca di layout
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('navCounts', $user ? [
                'notifications' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'inbox' => Conversation::where('unread_count', '>', 0)->count(),
            ] : ['notifications' => 0, 'inbox' => 0]);
        });
    }
}
