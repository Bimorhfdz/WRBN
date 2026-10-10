<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->guardProductionConfiguration();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json(
                    ['message' => 'Terlalu banyak percobaan. Coba lagi nanti.'],
                    429,
                    $headers
                ));
        });

        RateLimiter::for('auth-api', function (Request $request) {
            return Limit::perMinutes(15 * 60, 30)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json(
                    ['message' => 'Terlalu banyak percobaan. Coba lagi nanti.'],
                    429,
                    $headers
                ));
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinutes(15 * 60, 15)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json(
                    ['message' => 'Terlalu banyak percobaan login admin. Coba lagi nanti.'],
                    429,
                    $headers
                ));
        });
    }

    /**
     * Production refuses to start with a missing APP_KEY or with a dummy
     * payment provider that has not been explicitly allowed.
     */
    private function guardProductionConfiguration(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if ((string) config('app.key') === '') {
            throw new \RuntimeException('APP_KEY kosong. Set APP_KEY sebelum menjalankan aplikasi di production.');
        }

        if (config('wrbn.payment_provider') === 'dummy' && ! config('wrbn.allow_dummy_pay')) {
            throw new \RuntimeException('PAYMENT_PROVIDER=dummy tidak diizinkan di production tanpa ALLOW_DUMMY_PAY=true.');
        }
    }
}
