<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->withSchedule(function (Schedule $schedule) {
        Schedule::command('app:tg_parse:private')->hourly()->withoutOverlapping();
        Schedule::command('app:tg_parse:company')->hourly()->withoutOverlapping();
        Schedule::command('app:ai_parse:private')->everyTwoHours()->runInBackground()->withoutOverlapping();
        Schedule::command('app:ai_parse:company')->hourly()->runInBackground()->withoutOverlapping();
    })->create();
