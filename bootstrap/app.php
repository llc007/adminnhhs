<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RestrictGmailUsers;
use App\Http\Middleware\UpdateLastActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RestrictGmailUsers::class,
            UpdateLastActivity::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/mail',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
