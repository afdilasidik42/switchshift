<?php

use App\Http\Middleware\AccountSession;
use App\Http\Middleware\ShareInertiaData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AccountSession::class, ShareInertiaData::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
        $exceptions->render(function (DomainException $exception, Request $request) {
            throw ValidationException::withMessages(['action' => $exception->getMessage()]);
        });
    })->create();
