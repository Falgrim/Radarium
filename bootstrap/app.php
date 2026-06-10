<?php

use App\Infrastructures\Facades\Repositories;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )->withMiddleware(function (Middleware $middleware) {
        //
    })->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Сессия устарела. Обновите страницу и войдите снова.',
                ], 419);
            }

            return redirect('/')->with(
                'csrf_alert',
                'Сессия истекла или устарела защита формы. Вы можете снова войти в систему.'
            );
        });
    })->withSchedule(function (Schedule $schedule) {

        try {
            $readSourceCron = Repositories::setting()->findByName('read_source_cron');
        } catch (\Throwable) {
            // Нет БД/таблицы на этапе migrate или в PHPUnit — fallback как при пустой настройке
            $readSourceCron = null;
        }

        if ($readSourceCron?->value) {
            if ($readSourceCron->value <= 59) {
                // Каждые X минут
                $schedule->cron('*/'.$readSourceCron->value.' * * * *')
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:tg_parse:builder')->withoutOverlapping();
                        $schedule->command('app:tg_parse:company')->withoutOverlapping();
                        $schedule->command('app:vk_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:vk_parse:builder')->withoutOverlapping();
                        $schedule->command('app:vk_parse:company')->withoutOverlapping();
                    });
            } elseif ($readSourceCron->value <= 1439) {
                // Каждые Х часов
                $schedule->cron('0 */'.ceil($readSourceCron->value / 60).' * * *')
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:tg_parse:builder')->withoutOverlapping();
                        $schedule->command('app:tg_parse:company')->withoutOverlapping();
                        $schedule->command('app:vk_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:vk_parse:builder')->withoutOverlapping();
                        $schedule->command('app:vk_parse:company')->withoutOverlapping();
                    });
            } else {
                // Каждый день в 00:00
                $schedule->cron('0 0 * * *')
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:tg_parse:builder')->withoutOverlapping();
                        $schedule->command('app:tg_parse:company')->withoutOverlapping();
                        $schedule->command('app:vk_parse:specialist')->withoutOverlapping();
                        $schedule->command('app:vk_parse:builder')->withoutOverlapping();
                        $schedule->command('app:vk_parse:company')->withoutOverlapping();
                    });
            }
        } else {
            $schedule->command('app:tg_parse:specialist')->withoutOverlapping()->hourly();
            $schedule->command('app:tg_parse:builder')->withoutOverlapping()->hourly();
            $schedule->command('app:tg_parse:company')->withoutOverlapping()->hourly();
            $schedule->command('app:vk_parse:specialist')->withoutOverlapping()->hourly();
            $schedule->command('app:vk_parse:builder')->withoutOverlapping()->hourly();
            $schedule->command('app:vk_parse:company')->withoutOverlapping()->hourly();
        }

        // Отправка запросов в ИИ
        $schedule->everyThirtyMinutes()
            ->withoutOverlapping()
            ->group(function (Schedule $schedule) {
                $schedule->command('app:ai_parse:specialist');
                $schedule->command('app:ai_parse:builder');
                $schedule->command('app:ai_parse:company');
            });

        // Синхронизация с Тубус
        $schedule->command('app:tubus')->hourly()->withoutOverlapping();

        $schedule->command('app:tariff:users')->withoutOverlapping()->everyFifteenMinutes();

        // Проверка статуса новых платежей
        $schedule->command('app:payments:check')->withoutOverlapping()->everyTwoMinutes();

        // Отправка текста в ТГ чаты (отключить: SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false в .env)
        if (filter_var(env('SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $schedule->command('app:tg_chat:send_company')->withoutOverlapping()->everyMinute();
        }

        // Удаление просроченных токенов восстановления паролей
        $schedule->command('auth:clear-resets')->withoutOverlapping()->everyFifteenMinutes();
    })->create();
