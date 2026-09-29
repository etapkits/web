<?php

use App\Exceptions\BoardActionException;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\AuthenticateEtaotp;
use App\Http\Middleware\EndInactiveOrganizationSessions;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device' => AuthenticateDevice::class,
            'etaotp' => AuthenticateEtaotp::class,
        ]);

        $middleware->web(append: [EndInactiveOrganizationSessions::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EndInactiveOrganizationSessions::class);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('super') || $request->is('super/*') || $request->is('api/super/*')) {
                return route('super.login');
            }

            if ($request->is('ogretmen') || $request->is('ogretmen/*') || $request->is('api/ogretmen/*')) {
                return route('home');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if (Auth::guard('super')->check() && ($request->is('super') || $request->is('super/*') || $request->is('api/super/*'))) {
                return route('super.organizations');
            }

            if (Auth::guard('teacher')->check() && ($request->is('ogretmen') || $request->is('ogretmen/*') || $request->is('api/ogretmen/*'))) {
                return route('teacher.panel');
            }

            if (Auth::guard('web')->check()) {
                return route('panel');
            }

            if (Auth::guard('teacher')->check()) {
                return route('teacher.panel');
            }

            if (Auth::guard('super')->check()) {
                return route('super.organizations');
            }

            return route('panel');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (BoardActionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        });
    })->create();
