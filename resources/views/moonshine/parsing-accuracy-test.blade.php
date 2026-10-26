@php
    /** @var \App\Models\ParsingTestRun|null $run */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|null $messages */
    use App\Enum\ParsingTestRunStatus;

    $isActive = $isActive ?? false;
    $isStopping = $isStopping ?? false;

    $statusLabel = null;
    $statusColor = 'gray';
    $goal = 0;
    $percent = 0;
    $progressColor = 'primary';

    if ($run) {
        $statusLabel = $isStopping ? 'Останавливается' : $run->status->label();
        $statusColor = $isStopping ? 'warning' : match ($run->status) {
            ParsingTestRunStatus::Queued => 'gray',
            ParsingTestRunStatus::Running => 'info',
            ParsingTestRunStatus::Completed => 'success',
            ParsingTestRunStatus::Failed => 'error',
            ParsingTestRunStatus::Cancelled => 'secondary',
        };
        $goal = (int) ($run->sampled_count ?: $run->sample_size);
        $percent = $goal > 0 ? (int) min(100, round($run->processed_count / $goal * 100)) : 0;
        $progressColor = match (true) {
            $isStopping => 'warning',
            $run->status === ParsingTestRunStatus::Completed => 'success',
            $run->status === ParsingTestRunStatus::Failed => 'error',
            default => 'primary',
        };
    }

    $resultCount = $messages?->total();
@endphp

<style>
    .pat-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem 1.5rem;
    }
    .pat-copy {
        flex: 1 1 28rem;
        max-width: 65ch;
        font-size: 0.875rem;
        line-height: 1.55;
    }
    .pat-actions {
        flex: 0 0 auto;
    }
    .pat-actions form {
        margin: 0;
    }
    .pat-details {
        margin-top: 0.5rem;
    }
    .pat-details summary {
        cursor: pointer;
    }
    .pat-details p {
        margin: 0.5rem 0 0;
        opacity: 0.85;
    }
    .pat-run {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 1.25rem;
    }
    .pat-statusline {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
    }
    .pat-count {
        font-variant-numeric: tabular-nums;
    }
    .pat-hint {
        margin: 0;
        font-size: 0.75rem;
        opacity: 0.75;
    }
    .pat-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pat-metric {
        padding: 0.75rem 1rem;
    }
    .pat-metric:nth-child(odd) {
        border-right: 1px solid color-mix(in srgb, currentColor 16%, transparent);
    }
    .pat-metric:nth-child(n + 3) {
        border-top: 1px solid color-mix(in srgb, currentColor 16%, transparent);
    }
    .pat-metric-label {
        font-size: 0.75rem;
        line-height: 1.3;
        opacity: 0.75;
    }
    .pat-metric-value {
        margin-top: 0.25rem;
        font-size: 1.25rem;
        font-weight: 600;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }
    .pat-meta {
        display: grid;
        grid-template-columns: minmax(7.5rem, 11rem) minmax(0, 1fr);
        gap: 0.4rem 1rem;
        margin: 0;
        font-size: 0.875rem;
    }
    .pat-meta dt {
        opacity: 0.75;
    }
    .pat-meta dd {
        margin: 0;
        overflow-wrap: anywhere;
    }
    .pat-results {
        width: 100%;
        overflow-x: auto;
    }
    .pat-table {
        width: 100%;
        table-layout: fixed;
    }
    .table-list.pat-table thead th,
    .table-list.pat-table tbody td {
        white-space: normal;
        max-width: none;
        vertical-align: top;
        overflow-wrap: break-word;
        padding: 0.75rem 0.65rem;
    }
    .pat-table .badge {
        white-space: normal;
        height: auto;
        max-width: 100%;
    }
    .pat-table th:nth-child(1),
    .pat-table td:nth-child(1) { width: 7.25rem; }
    .pat-table th:nth-child(2),
    .pat-table td:nth-child(2) { width: 18%; }
    .pat-table th:nth-child(3),
    .pat-table td:nth-child(3) { width: 16%; }
    .pat-table th:nth-child(5),
    .pat-table td:nth-child(5) { width: 22%; }
    .pat-message {
        white-space: normal;
        min-width: 0;
    }
    .pat-post {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        max-height: 9rem;
        overflow: auto;
    }
    .pat-sub {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        opacity: 0.75;
    }
    .pat-error {
        margin-top: 0.35rem;
        color: #991b1b;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .dark .pat-error {
        color: #fecaca;
    }
    .pat-reason {
        margin-top: 0.35rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        opacity: 0.85;
    }
    @media (min-width: 1024px) {
        .pat-metrics {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .pat-metric:nth-child(odd),
        .pat-metric:nth-child(even) {
            border-top: 0;
            border-right: 1px solid color-mix(in srgb, currentColor 16%, transparent);
        }
        .pat-metric:last-child {
            border-right: 0;
        }
    }
</style>

<div class="space-y-6 p-4">
    @if($isActive)
        <script>
            setTimeout(function () { window.location.reload(); }, 8000);
        </script>
    @endif

    <x-moonshine::box>
        <div class="pat-toolbar">
            <div class="pat-copy">
                <p>
                    Проверка отбора сообщений строительной отрасли. Тест берёт до 300 случайных постов Telegram,
                    по одному у разных авторов, и не меняет каталог.
                </p>
                <details class="pat-details">
                    <summary class="text-primary">Как проходит проверка</summary>
                    <p>
                        Сначала модель решает, предложение ли это услуги. Полная карточка строится только для таких
                        сообщений. Если карточку разобрать не удалось, строка остаётся в таблице с пометкой ошибки извлечения.
                    </p>
                </details>
            </div>
            <div class="pat-actions">
                @if($isStopping)
                    <button type="button" class="btn btn-warning" disabled>Останавливается</button>
                @elseif($isActive)
                    <form method="post" action="{{ $cancelUrl }}"
                          onsubmit="return confirm('Прервать текущий тест? Уже разобранные строки останутся.');">
                        @csrf
                        <button type="submit" class="btn btn-error">Прервать тест</button>
                    </form>
                @else
                    <form method="post" action="{{ $startUrl }}"
                          onsubmit="return confirm('Таблица результатов будет очищена. Запустить новый тест на 300 сообщениях?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">Запустить тест</button>
                    </form>
                @endif
            </div>
        </div>

        @if($error)
            <x-moonshine::alert type="error" icon="heroicons.no-symbol">{{ $error }}</x-moonshine::alert>
        @endif

        @if($run)
            <div class="pat-run">
                <div class="pat-statusline" role="status">
                    <x-moonshine::badge :color="$statusColor">{{ $statusLabel }}</x-moonshine::badge>
                    <span class="pat-count">Обработано {{ $run->processed_count }} из {{ $goal }}</span>
                </div>
                <x-moonshine::progress-bar :value="$percent" :color="$progressColor" />
                @if($isActive)
                    <p class="pat-hint">Страница обновляется каждые 8 секунд.</p>
                @endif

                @if($isStopping)
                    <x-moonshine::alert type="info" icon="heroicons.information-circle">
                        Текущее сообщение ещё дочитывается, затем прогон остановится. Уже разобранные строки сохранятся.
                    </x-moonshine::alert>
                @endif

                @if($run->error_message)
                    <x-moonshine::alert
                        :type="$run->status === ParsingTestRunStatus::Failed ? 'error' : 'warning'"
                        icon="heroicons.no-symbol"
                    >{{ $run->error_message }}</x-moonshine::alert>
                @endif

                @if($run->last_error)
                    <x-moonshine::alert type="error" icon="heroicons.no-symbol">
                        Последняя ошибка: {{ $run->last_error }}
                    </x-moonshine::alert>
                @endif

                <div class="pat-metrics">
                    <div class="pat-metric">
                        <div class="pat-metric-label">Целевые</div>
                        <div class="pat-metric-value">{{ $run->target_count }}</div>
                    </div>
                    <div class="pat-metric">
                        <div class="pat-metric-label">Ошибки разбора</div>
                        <div class="pat-metric-value">{{ $run->error_count }}</div>
                    </div>
                    <div class="pat-metric">
                        <div class="pat-metric-label">Ошибки извлечения</div>
                        <div class="pat-metric-value">{{ $run->extraction_error_count }}</div>
                    </div>
                    <div class="pat-metric">
                        <div class="pat-metric-label" title="Снято эвристикой найма после ответа модели">Снято эвристикой</div>
                        <div class="pat-metric-value">{{ $run->heuristic_reject_count }}</div>
                    </div>
                </div>

                <dl class="pat-meta">
                    <dt>Модель</dt>
                    <dd>{{ $run->model ?: '—' }}</dd>
                    <dt>Промпт</dt>
                    <dd>{{ $run->prompt_label ?: '—' }}</dd>
                    <dt>Старт</dt>
                    <dd>{{ $run->started_at?->format('d.m.Y H:i:s') ?: '—' }}</dd>
                    <dt>Окончание</dt>
                    <dd>{{ $run->finished_at?->format('d.m.Y H:i:s') ?: '—' }}</dd>
                    @if($run->note)
                        <dt>Заметка</dt>
                        <dd>{{ $run->note }}</dd>
                    @endif
                </dl>
            </div>
        @else
            <x-moonshine::alert type="default" icon="heroicons.information-circle">
                Тест ещё не запускался.
            </x-moonshine::alert>
        @endif
    </x-moonshine::box>

    <x-moonshine::box :title="$resultCount === null ? 'Целевые сообщения' : 'Целевые сообщения · '.$resultCount">
        @if($messages && $messages->count() > 0)
            <div class="pat-results">
                <table class="table-list pat-table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Автор</th>
                        <th>Канал</th>
                        <th>Сообщение</th>
                        <th>Решение модели</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($messages as $row)
                        <tr>
                            <td>{{ $row->post_date?->format('d.m.Y H:i') ?: '—' }}</td>
                            <td>
                                <div>{{ $row->author_name ?: 'без имени' }}</div>
                                @if($row->author_login)
                                    <div class="pat-sub">{{ '@'.$row->author_login }}</div>
                                @endif
                                @if($row->api_post_user_id)
                                    <a class="text-primary pat-sub" href="{{ $authorDetailUrl($row->api_post_user_id) }}">Карточка автора</a>
                                @endif
                            </td>
                            <td>{{ $row->channel_title ?: '—' }}</td>
                            <td class="pat-message">
                                <div class="pat-post">{{ $row->post_text }}</div>
                                @if($row->api_channel_post_id)
                                    <a class="text-primary pat-sub" href="{{ $postFormUrl($row->api_channel_post_id) }}">Исходный пост</a>
                                @endif
                            </td>
                            <td>
                                @if($row->ai_type)
                                    <x-moonshine::badge color="info">{{ $row->ai_type }}</x-moonshine::badge>
                                @else
                                    —
                                @endif
                                @if($row->extraction_error)
                                    <div class="pat-error">Ошибка извлечения: {{ $row->extraction_error }}</div>
                                @endif
                                @if($row->ai_reason)
                                    <div class="pat-reason">{{ $row->ai_reason }}</div>
                                @endif
                                @if($row->specialities)
                                    <div class="pat-sub">Специализации: {{ $row->specialities }}</div>
                                @endif
                                @if($row->model_reply)
                                    <details class="pat-details">
                                        <summary class="text-primary">Ответ модели</summary>
                                        <pre class="pat-reason">{{ $row->model_reply }}</pre>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if($messages->hasPages())
                <x-moonshine::pagination :paginator="$messages" :elements="$messages->elements()" />
            @endif
        @else
            <x-moonshine::alert type="default" icon="heroicons.no-symbol">
                @if($isActive)
                    Целевых сообщений пока нет. Модель ещё разбирает выборку.
                @elseif($run)
                    Модель не отметила целевых сообщений в этой выборке.
                @else
                    Здесь появятся сообщения, которые модель сочтёт целевыми.
                @endif
            </x-moonshine::alert>
        @endif
    </x-moonshine::box>
</div>
