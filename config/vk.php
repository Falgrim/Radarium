<?php

/**
 * Интеграция VK API для чтения стен сообществ.
 *
 * Продуктовые умолчания (переопределяются env):
 * — токен: VK_SERVICE_TOKEN;
 * — репосты: сохраняем (текст поста + цепочка copy_history для ИИ), опционально VK_SKIP_REPOSTS=true;
 * — аватары: загрузка photo_200 в storage (каталог как у Telegram, префикс имени vk_).
 */
return [

    'api_version' => env('VK_API_VERSION', '5.199'),

    /**
     * Сервисный ключ доступа или пользовательский токен с правом чтения стены.
     */
    'service_token' => env('VK_SERVICE_TOKEN', ''),

    'http_timeout' => (int) env('VK_HTTP_TIMEOUT', 15),

    /**
     * Минимальная пауза между вызовами method (мкс), ~3 запроса/с без расширенных лимитов.
     */
    'min_interval_us' => (int) env('VK_MIN_INTERVAL_US', 350_000),

    /**
     * Ограничение страниц wall.get за один запуск cron (count до 100 на страницу).
     */
    'max_wall_pages_per_run' => (int) env('VK_MAX_WALL_PAGES', 15),

    'skip_reposts' => filter_var(env('VK_SKIP_REPOSTS', 'false'), FILTER_VALIDATE_BOOLEAN),

    'download_avatars' => filter_var(env('VK_DOWNLOAD_AVATARS', 'true'), FILTER_VALIDATE_BOOLEAN),
];
