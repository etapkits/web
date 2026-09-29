<?php

namespace App\Providers;

use App\Support\PhoneNumber;
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
        RateLimiter::for('device', function (Request $request) {
            $token = (string) $request->bearerToken();
            $key = $token !== '' ? hash('sha256', $token) : (string) $request->ip();

            return Limit::perMinute(180)->by($key);
        });

        RateLimiter::for('device-register', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        RateLimiter::for('teacher-otp', function (Request $request) {
            $phone = PhoneNumber::normalize((string) $request->input('phone')) ?? (string) $request->ip();

            return [
                Limit::perMinutes(15, 5)->by('otp-phone:'.$phone),
                Limit::perMinutes(15, 10)->by('otp-ip:'.$request->ip()),
            ];
        });
    }
}
