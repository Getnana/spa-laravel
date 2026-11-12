<?php

use App\Http\Middleware\SpaAuthenticate;
use App\Http\Middleware\SpaRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['spa.auth' => SpaAuthenticate::class, 'spa.role' => SpaRole::class]);
        $middleware->trimStrings(except: ['new_password', 'confirm_password']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })
    ->create();
