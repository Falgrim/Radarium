<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\ParsesDateOption;
use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Диагностика свежести публичного каталога строителей: только чтение, ничего не меняет.
 *
 * Отвечает на вопрос «почему в каталоге нет свежих сообщений», разделяя пайплайн на стадии:
 * парсинг источников → очередь ИИ → результат ИИ → статус карточки → витрина. Стадия, на которой
 * числа обрываются, и есть место поломки.
 *
 * Пришла на смену разовому скрипту `scripts/diag_builders_catalog.php`: в команде статусы берутся
 * из enum'ов, а голова очереди читается в том же порядке, в котором её разбирает `app:ai_parse:builder`.
 */
class DiagnoseBuildersCatalog extends Command
{
    use ParsesDateOption;

    /** Размер партии `app:ai_parse:builder` — голову очереди смотрим ровно на эту глубину. */
    private const QUEUE_BATCH = 100;

    /** Глубина месячных разрезов по умолчанию. */
    private const DEFAULT_MONTHS = 4;

    protected $signature = 'app:catalog:diagnose-builders
                            {--since= : Начало месячных разрезов в формате Y-m-d, по умолчанию начало месяца '.self::DEFAULT_MONTHS.' мес. назад}
                            {--head= : Сколько постов из головы очереди разобрать, по умолчанию размер партии ИИ ('.self::QUEUE_BATCH.')}';

    protected $description = 'Read-only диагностика: почему в публичном каталоге строителей нет свежих сообщений';

    public function handle(): int
    {
        $since = $this->resolveSince();
        if ($since === null) {
            return self::INVALID;
        }

        $head = max(1, (int) ($this->option('head') ?: self::QUEUE_BATCH));

        $builderChannel = ApiDataTypeEnum::Builder->value;
        $inQueue = ApiChannelPostStatusEnum::InQueue->value;
        $complete = ApiChannelPostStatusEnum::Complete->value;
        $cardActive = ApiPostAiStatusEnum::Active->value;
        $channelActive = ApiChannelStatusEnum::Active->value;
        $aiActive = ApiAiStatusEnum::Active->value;

        // Посты могли получить softDeletes позже команды — без проверки диагностика упала бы на неизвестной колонке.
        $postAlive = Schema::hasColumn('api_channel_posts', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';

        $this->line('Диагностика каталога строителей, время сервера: '.now()->format('Y-m-d H:i:s'));
        $this->line('Месячные разрезы с '.$since->format('Y-m-d').', голова очереди: '.$head.' постов');

        $this->section('1. Каналы строителей: состояние курсора парсинга', "
            SELECT
                CASE status WHEN {$channelActive} THEN 'Active' ELSE 'Disabled' END AS channel_status,
                channel_source,
                COUNT(*)                                                            AS channels,
                SUM(api_ai_id IS NULL)                                              AS without_ai,
                MIN(last_date_check)                                                AS cursor_oldest,
                MAX(last_date_check)                                                AS cursor_newest
            FROM api_channels
            WHERE is_company = {$builderChannel}
            GROUP BY status, channel_source
            ORDER BY channel_status, channel_source
        ");

        $this->section('2. Приток постов строителей за последние 14 дней (по created_at)', "
            SELECT
                DATE(p.created_at)  AS created_day,
                COUNT(*)            AS posts,
                MIN(p.post_date)    AS post_date_min,
                MAX(p.post_date)    AS post_date_max
            FROM api_channel_posts p
            JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = {$builderChannel}
            WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY){$postAlive}
            GROUP BY created_day
            ORDER BY created_day DESC
        ");

        $this->section('3. Посты строителей по месяцам и статусу ИИ', "
            SELECT
                DATE_FORMAT(p.post_date, '%Y-%m')                                   AS post_month,
                COUNT(*)                                                            AS total,
                SUM(p.ai_parse_status = {$inQueue})                                 AS in_queue,
                SUM(p.ai_parse_status = {$complete})                                AS complete,
                SUM(p.ai_parse_status = ".ApiChannelPostStatusEnum::Error->value.")     AS error,
                SUM(p.ai_parse_status = ".ApiChannelPostStatusEnum::Empty->value.")     AS empty_result,
                SUM(p.ai_parse_status = ".ApiChannelPostStatusEnum::DontMatch->value.") AS dont_match,
                SUM(p.ai_parse_status = ".ApiChannelPostStatusEnum::Duplicate->value.") AS duplicate
            FROM api_channel_posts p
            JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = {$builderChannel}
            WHERE p.post_date >= ?{$postAlive}
            GROUP BY post_month
            ORDER BY post_month
        ", [$since->format('Y-m-d')]);

        $this->section('4. Очередь ИИ по строителям', "
            SELECT
                SUM(p.ai_parse_status = {$inQueue})                              AS in_queue_now,
                MIN(CASE WHEN p.ai_parse_status = {$inQueue} THEN p.post_date END) AS queue_oldest_post,
                MAX(CASE WHEN p.ai_parse_status = {$inQueue} THEN p.post_date END) AS queue_newest_post,
                MAX(p.ai_date)                                                   AS last_ai_run,
                MAX(p.post_date)                                                 AS newest_post_any_status
            FROM api_channel_posts p
            JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = {$builderChannel}
            WHERE 1 = 1{$postAlive}
        ");

        $this->section('5. Карточки Builder по месяцам и статусу', "
            SELECT
                DATE_FORMAT(b.post_date, '%Y-%m')                           AS post_month,
                COUNT(*)                                                    AS cards,
                SUM(b.status = {$cardActive})                               AS active,
                SUM(b.status = ".ApiPostAiStatusEnum::InModeration->value.")   AS in_moderation,
                SUM(b.status = ".ApiPostAiStatusEnum::Disabled->value.")       AS disabled,
                SUM(b.status = ".ApiPostAiStatusEnum::Error->value.")          AS error
            FROM builders b
            WHERE b.deleted_at IS NULL AND b.post_date >= ?
            GROUP BY post_month
            ORDER BY post_month
        ", [$since->format('Y-m-d')]);

        $this->section('6. Витрина каталога: посты, попадающие в выдачу', "
            SELECT
                DATE_FORMAT(p.post_date, '%Y-%m')   AS post_month,
                COUNT(*)                            AS visible_posts,
                COUNT(DISTINCT b.api_post_user_id)  AS authors
            FROM builders b
            JOIN api_channel_posts p ON p.id = b.api_channel_post_id
            WHERE b.deleted_at IS NULL
              AND b.status = {$cardActive}
              AND b.api_channel_post_id > 0
              AND p.ai_parse_status = {$complete}{$postAlive}
              AND p.post_date >= ?
            GROUP BY post_month
            ORDER BY post_month
        ", [$since->format('Y-m-d')]);

        $this->section('7. Топ-10 авторов каталога по дате последнего сообщения', "
            SELECT
                b.api_post_user_id  AS author_id,
                MAX(p.post_date)    AS last_message,
                COUNT(*)            AS active_cards
            FROM builders b
            JOIN api_channel_posts p ON p.id = b.api_channel_post_id
            WHERE b.deleted_at IS NULL
              AND b.status = {$cardActive}
              AND b.api_channel_post_id > 0
              AND p.ai_parse_status = {$complete}{$postAlive}
            GROUP BY b.api_post_user_id
            ORDER BY last_message DESC
            LIMIT 10
        ");

        $this->section('8. Провайдеры ИИ, привязанные к каналам строителей', "
            SELECT
                ai.id                                                           AS ai_id,
                LEFT(ai.title, 24)                                              AS ai_title,
                ai.api_source                                                   AS api_source,
                CASE ai.status WHEN {$aiActive} THEN 'Active' ELSE 'DISABLED' END AS ai_status,
                COUNT(DISTINCT c.id)                                            AS builder_channels,
                (
                    SELECT COUNT(*)
                    FROM api_channel_posts p2
                    JOIN api_channels c2 ON c2.id = p2.api_channel_id AND c2.is_company = {$builderChannel}
                    WHERE c2.api_ai_id = ai.id AND p2.ai_parse_status = {$inQueue}
                )                                                               AS posts_in_queue
            FROM api_ais ai
            JOIN api_channels c ON c.api_ai_id = ai.id AND c.is_company = {$builderChannel}
            GROUP BY ai.id, ai.title, ai.api_source, ai.status
            ORDER BY posts_in_queue DESC
        ");

        $knownSources = implode(', ', array_map(
            static fn (ApiAiSourceEnum $source): string => "'".$source->value."'",
            ApiAiSourceEnum::cases()
        ));

        // Ровно та партия, которую заберёт следующий запуск ИИ: порядок совпадает с AiBuilderPosts.
        $headOfQueue = "
            SELECT
                p.id            AS post_id,
                p.post_date     AS post_date,
                c.id            AS channel_id,
                ai.id           AS ai_id,
                ai.status       AS ai_status,
                ai.api_source   AS api_source
            FROM api_channel_posts p
            JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = {$builderChannel}
            LEFT JOIN api_ais ai ON ai.id = c.api_ai_id
            WHERE p.ai_parse_status = {$inQueue}{$postAlive}
            ORDER BY p.post_date DESC
            LIMIT {$head}
        ";

        $this->section('9. Голова очереди (следующая партия ИИ): дойдёт ли команда до обработки', "
            SELECT
                CASE
                    WHEN h.ai_id IS NULL                            THEN 'нет api_ai'
                    WHEN h.ai_status <> {$aiActive}                 THEN 'ИИ отключён (continue)'
                    WHEN h.api_source NOT IN ({$knownSources})      THEN 'неизвестный источник (continue)'
                    ELSE 'ИИ активен (обрабатывается)'
                END                 AS head_reason,
                COUNT(*)            AS posts_in_head,
                MIN(h.post_date)    AS oldest,
                MAX(h.post_date)    AS newest
            FROM ({$headOfQueue}) h
            GROUP BY head_reason
            ORDER BY posts_in_head DESC
        ");

        $this->section('10. Голова очереди по каналам', "
            SELECT
                h.channel_id        AS channel_id,
                h.api_source        AS api_source,
                CASE
                    WHEN h.ai_status = {$aiActive} THEN 'Active'
                    WHEN h.ai_status IS NULL       THEN '-'
                    ELSE 'DISABLED'
                END                 AS ai_status,
                COUNT(*)            AS posts_in_head,
                MIN(h.post_date)    AS oldest,
                MAX(h.post_date)    AS newest
            FROM ({$headOfQueue}) h
            GROUP BY h.channel_id, h.api_source, h.ai_status
            ORDER BY posts_in_head DESC
        ");

        $this->section('11. Обработано ИИ за последние 24 часа (по ai_date)', "
            SELECT
                CASE p.ai_parse_status
                    WHEN {$inQueue}  THEN 'InQueue'
                    WHEN {$complete} THEN 'Complete'
                    WHEN ".ApiChannelPostStatusEnum::Error->value."     THEN 'Error'
                    WHEN ".ApiChannelPostStatusEnum::Empty->value."     THEN 'Empty'
                    WHEN ".ApiChannelPostStatusEnum::DontMatch->value." THEN 'DontMatch'
                    WHEN ".ApiChannelPostStatusEnum::Duplicate->value." THEN 'Duplicate'
                END                 AS status,
                COUNT(*)            AS posts,
                MIN(p.post_date)    AS oldest_post,
                MAX(p.post_date)    AS newest_post
            FROM api_channel_posts p
            JOIN api_channels c ON c.id = p.api_channel_id AND c.is_company = {$builderChannel}
            WHERE p.ai_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR){$postAlive}
            GROUP BY p.ai_parse_status
            ORDER BY posts DESC
        ");

        if (Schema::hasTable('jobs')) {
            $this->section('12. Очередь Laravel', '
                SELECT
                    (SELECT COUNT(*) FROM jobs)                          AS jobs_pending,
                    (SELECT COUNT(*) FROM failed_jobs)                   AS jobs_failed,
                    (SELECT MIN(FROM_UNIXTIME(created_at)) FROM jobs)    AS oldest_job
            ');
        }

        $this->newLine();
        $this->info('Готово. Ничего не изменено: команда только читает данные.');

        return self::SUCCESS;
    }

    private function resolveSince(): ?CarbonImmutable
    {
        $raw = trim((string) $this->option('since'));

        if ($raw === '') {
            return CarbonImmutable::now()->subMonths(self::DEFAULT_MONTHS)->startOfMonth();
        }

        return $this->parseDateOption('since', $raw);
    }

    /**
     * @param  list<scalar>  $bindings
     */
    private function section(string $title, string $sql, array $bindings = []): void
    {
        $this->newLine();
        $this->info('=== '.$title.' ===');

        $rows = array_map(
            static fn (object $row): array => (array) $row,
            DB::select($sql, $bindings)
        );

        if ($rows === []) {
            $this->line('(пусто)');

            return;
        }

        $this->table(array_keys($rows[0]), $rows);
    }
}
