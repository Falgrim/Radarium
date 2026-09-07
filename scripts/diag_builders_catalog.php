<?php

declare(strict_types=1);

/**
 * Диагностика свежести публичного каталога строителей: только чтение, ничего не меняет.
 *
 * Запуск на проде из корня проекта: php scripts/diag_builders_catalog.php
 *
 * Отвечает на вопрос «почему в каталоге нет свежих сообщений», разделяя стадии пайплайна:
 * парсинг источников → очередь ИИ → результат ИИ → статус карточки → витрина.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const BUILDER_CHANNEL = 2;      // api_channels.is_company = ApiDataTypeEnum::Builder
const CHANNEL_ACTIVE = 1;       // api_channels.status = ApiChannelStatusEnum::Active
const POST_COMPLETE = 1;        // api_channel_posts.ai_parse_status = Complete
const POST_IN_QUEUE = 0;        // api_channel_posts.ai_parse_status = InQueue
const CARD_ACTIVE = 2;          // builders.status = ApiPostAiStatusEnum::Active

$postsSoftDeleted = Schema::hasColumn('api_channel_posts', 'deleted_at');
$postAlive = $postsSoftDeleted ? ' AND p.deleted_at IS NULL' : '';

/**
 * @param  array<int, object>  $rows
 */
function table(string $title, array $rows): void
{
    echo "\n=== {$title} ===\n";

    if ($rows === []) {
        echo "(пусто)\n";

        return;
    }

    $columns = array_keys(get_object_vars($rows[0]));
    $width = [];
    foreach ($columns as $column) {
        $width[$column] = mb_strlen($column);
        foreach ($rows as $row) {
            $width[$column] = max($width[$column], mb_strlen((string) $row->{$column}));
        }
    }

    $line = static function (array $cells) use ($columns, $width): string {
        $out = [];
        foreach ($columns as $column) {
            $value = (string) ($cells[$column] ?? '');
            $out[] = $value.str_repeat(' ', $width[$column] - mb_strlen($value));
        }

        return '  '.implode('  |  ', $out);
    };

    echo $line(array_combine($columns, $columns))."\n";
    echo '  '.str_repeat('-', array_sum($width) + 5 * (count($columns) - 1))."\n";
    foreach ($rows as $row) {
        echo $line(get_object_vars($row))."\n";
    }
}

echo "Диагностика каталога строителей, время сервера: ".now()->format('Y-m-d H:i:s')."\n";

// 1. Источники: активны ли каналы и как далеко продвинулся курсор парсинга.
table('1. Каналы строителей: состояние курсора парсинга', DB::select("
    SELECT
        CASE status WHEN 1 THEN 'Active' ELSE 'Disabled' END AS channel_status,
        channel_source,
        COUNT(*)                                             AS channels,
        SUM(api_ai_id IS NULL)                               AS without_ai,
        MIN(last_date_check)                                 AS cursor_oldest,
        MAX(last_date_check)                                 AS cursor_newest
    FROM api_channels
    WHERE is_company = ".BUILDER_CHANNEL."
    GROUP BY status, channel_source
    ORDER BY channel_status, channel_source
"));

// 2. Реально ли парсер приносит новые сообщения (по дате появления строки в БД).
table('2. Приток постов строителей за последние 14 дней (по created_at)', DB::select("
    SELECT
        DATE(p.created_at)   AS created_day,
        COUNT(*)             AS posts,
        MIN(p.post_date)     AS post_date_min,
        MAX(p.post_date)     AS post_date_max
    FROM api_channel_posts p
    JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = ".BUILDER_CHANNEL."
    WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY){$postAlive}
    GROUP BY created_day
    ORDER BY created_day DESC
"));

// 3. Что ИИ сделал с постами: разбивка по месяцу сообщения и статусу обработки.
table('3. Посты строителей по месяцам и статусу ИИ (с мая)', DB::select("
    SELECT
        DATE_FORMAT(p.post_date, '%Y-%m')       AS post_month,
        COUNT(*)                                AS total,
        SUM(p.ai_parse_status = 0)              AS in_queue,
        SUM(p.ai_parse_status = 1)              AS complete,
        SUM(p.ai_parse_status = 2)              AS error,
        SUM(p.ai_parse_status = 3)              AS empty_result,
        SUM(p.ai_parse_status = 4)              AS dont_match,
        SUM(p.ai_parse_status = 5)              AS duplicate
    FROM api_channel_posts p
    JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = ".BUILDER_CHANNEL."
    WHERE p.post_date >= '2026-05-01'{$postAlive}
    GROUP BY post_month
    ORDER BY post_month
"));

// 4. Живёт ли очередь ИИ и когда он отработал в последний раз.
table('4. Очередь ИИ по строителям', DB::select("
    SELECT
        SUM(p.ai_parse_status = ".POST_IN_QUEUE.")                                  AS in_queue_now,
        MIN(CASE WHEN p.ai_parse_status = ".POST_IN_QUEUE." THEN p.post_date END)    AS queue_oldest_post,
        MAX(CASE WHEN p.ai_parse_status = ".POST_IN_QUEUE." THEN p.post_date END)    AS queue_newest_post,
        MAX(p.ai_date)                                                              AS last_ai_run,
        MAX(p.post_date)                                                            AS newest_post_any_status
    FROM api_channel_posts p
    JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = ".BUILDER_CHANNEL."
    WHERE 1 = 1{$postAlive}
"));

// 5. Ключевая развилка: карточки создаются, но с каким статусом (Active против модерации).
table('5. Карточки Builder по месяцам и статусу (с мая)', DB::select("
    SELECT
        DATE_FORMAT(b.post_date, '%Y-%m')   AS post_month,
        COUNT(*)                            AS cards,
        SUM(b.status = 2)                   AS active,
        SUM(b.status = 1)                   AS in_moderation,
        SUM(b.status = 0)                   AS disabled,
        SUM(b.status = 3)                   AS error
    FROM builders b
    WHERE b.deleted_at IS NULL AND b.post_date >= '2026-05-01'
    GROUP BY post_month
    ORDER BY post_month
"));

// 6. То, что реально видит витрина: активная карточка + исходный пост в статусе Complete.
table('6. Витрина каталога: посты, попадающие в выдачу (с мая)', DB::select("
    SELECT
        DATE_FORMAT(p.post_date, '%Y-%m')   AS post_month,
        COUNT(*)                            AS visible_posts,
        COUNT(DISTINCT b.api_post_user_id)  AS authors
    FROM builders b
    JOIN api_channel_posts p ON p.id = b.api_channel_post_id
    WHERE b.deleted_at IS NULL
      AND b.status = ".CARD_ACTIVE."
      AND b.api_channel_post_id > 0
      AND p.ai_parse_status = ".POST_COMPLETE."{$postAlive}
      AND p.post_date >= '2026-05-01'
    GROUP BY post_month
    ORDER BY post_month
"));

// 7. Первая страница каталога — сверка с тем, что видно в браузере.
table('7. Топ-10 авторов каталога по дате последнего сообщения', DB::select("
    SELECT
        b.api_post_user_id      AS author_id,
        MAX(p.post_date)        AS last_message,
        COUNT(*)                AS active_cards
    FROM builders b
    JOIN api_channel_posts p ON p.id = b.api_channel_post_id
    WHERE b.deleted_at IS NULL
      AND b.status = ".CARD_ACTIVE."
      AND b.api_channel_post_id > 0
      AND p.ai_parse_status = ".POST_COMPLETE."{$postAlive}
    GROUP BY b.api_post_user_id
    ORDER BY last_message DESC
    LIMIT 10
"));

// 9. Провайдеры ИИ строительных каналов: отключённый провайдер — это вечный InQueue без ai_date.
table('9. Провайдеры ИИ, привязанные к каналам строителей', DB::select("
    SELECT
        ai.id                                                       AS ai_id,
        LEFT(ai.title, 24)                                          AS ai_title,
        ai.api_source                                               AS api_source,
        CASE ai.status WHEN 1 THEN 'Active' ELSE 'DISABLED' END     AS ai_status,
        COUNT(DISTINCT c.id)                                        AS builder_channels,
        (
            SELECT COUNT(*)
            FROM api_channel_posts p2
            JOIN api_channels c2 ON c2.id = p2.api_channel_id AND c2.is_company = ".BUILDER_CHANNEL."
            WHERE c2.api_ai_id = ai.id AND p2.ai_parse_status = ".POST_IN_QUEUE."
        )                                                           AS posts_in_queue
    FROM api_ais ai
    JOIN api_channels c ON c.api_ai_id = ai.id AND c.is_company = ".BUILDER_CHANNEL."
    GROUP BY ai.id, ai.title, ai.api_source, ai.status
    ORDER BY posts_in_queue DESC
"));

// 10. Голова очереди: ровно те 100 постов, что забирает команда (post_date ASC, take(100)).
//     Если они из каналов с отключённым ИИ, команда пропускает их через continue и до свежих не доходит.
$headOfQueue = "
    SELECT
        p.id            AS post_id,
        p.post_date     AS post_date,
        c.id            AS channel_id,
        ai.id           AS ai_id,
        ai.status       AS ai_status,
        ai.api_source   AS api_source
    FROM api_channel_posts p
    JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = ".BUILDER_CHANNEL."
    LEFT JOIN api_ais ai ON ai.id = c.api_ai_id
    WHERE p.ai_parse_status = ".POST_IN_QUEUE."{$postAlive}
    ORDER BY p.post_date ASC
    LIMIT 100
";

table('10. Голова очереди (первые 100 постов): дойдёт ли команда до обработки', DB::select("
    SELECT
        CASE
            WHEN h.ai_id IS NULL                                        THEN 'нет api_ai'
            WHEN h.ai_status <> 1                                       THEN 'ИИ отключён (continue)'
            WHEN h.api_source NOT IN ('yandexgtp4', 'ollama_qwen')      THEN 'неизвестный источник (continue)'
            ELSE 'ИИ активен (обрабатывается)'
        END                     AS head_reason,
        COUNT(*)                AS posts_of_100,
        MIN(h.post_date)        AS oldest,
        MAX(h.post_date)        AS newest
    FROM ({$headOfQueue}) h
    GROUP BY head_reason
    ORDER BY posts_of_100 DESC
"));

table('10б. Голова очереди по каналам', DB::select("
    SELECT
        h.channel_id            AS channel_id,
        h.api_source            AS api_source,
        CASE WHEN h.ai_status = 1 THEN 'Active' WHEN h.ai_status IS NULL THEN '-' ELSE 'DISABLED' END AS ai_status,
        COUNT(*)                AS posts_of_100,
        MIN(h.post_date)        AS oldest
    FROM ({$headOfQueue}) h
    GROUP BY h.channel_id, h.api_source, h.ai_status
    ORDER BY posts_of_100 DESC
"));

// 11. Реальная пропускная способность ИИ за сутки: сколько постов получили финальный статус.
table('11. Обработано ИИ за последние 24 часа (по ai_date)', DB::select("
    SELECT
        CASE p.ai_parse_status
            WHEN 0 THEN 'InQueue'
            WHEN 1 THEN 'Complete'
            WHEN 2 THEN 'Error'
            WHEN 3 THEN 'Empty'
            WHEN 4 THEN 'DontMatch'
            WHEN 5 THEN 'Duplicate'
        END                     AS status,
        COUNT(*)                AS posts,
        MIN(p.post_date)        AS oldest_post,
        MAX(p.post_date)        AS newest_post
    FROM api_channel_posts p
    JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = ".BUILDER_CHANNEL."
    WHERE p.ai_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR){$postAlive}
    GROUP BY p.ai_parse_status
    ORDER BY posts DESC
"));

// 8. Фоновая очередь Laravel: нужна только для ручного requeue, но зависшие джобы тоже симптом.
if (Schema::hasTable('jobs')) {
    table('12. Очередь Laravel', DB::select('
        SELECT
            (SELECT COUNT(*) FROM jobs)         AS jobs_pending,
            (SELECT COUNT(*) FROM failed_jobs)  AS jobs_failed,
            (SELECT MIN(FROM_UNIXTIME(created_at)) FROM jobs) AS oldest_job
    '));
}

echo "\nГотово.\n";
