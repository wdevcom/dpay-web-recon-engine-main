<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Limit per konsument, nie per adres IP - usługi dpay wychodzą zwykle
         * spod jednego adresu, więc limit po IP karałby je nawzajem.
         * Tenant jest już rozpoznany, bo throttle idzie po uwierzytelnieniu.
         */
        RateLimiter::for('api', function (Request $request) {
            $tenant = $request->attributes->get('tenant');

            return Limit::perMinute(600)->by($tenant?->id ? 'tenant:'.$tenant->id : 'ip:'.$request->ip());
        });
    }
}
