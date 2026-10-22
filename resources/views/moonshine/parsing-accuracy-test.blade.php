@php
    /** @var \App\Models\ParsingTestRun|null $run */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|null $messages */
    $isActive = $isActive ?? false;
    $isStopping = $isStopping ?? false;
@endphp

<div class="space-y-6 p-4">
    @if($isActive)
        <script>
            setTimeout(function () { window.location.reload(); }, 8000);
        </script>
    @endif

    <section class="rounded-xl border border-gray-200 dark:border-dark-600 bg-white dark:bg-dark-800 shadow-sm">
        <div class="px-5 py-4 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-4xl text-sm text-gray-700 dark:text-gray-200">
                    Проверка отбора для строительной отрасли. Кнопка очищает таблицу и берёт до 300 случайных
                    сырых сообщений Telegram — по одному у разных авторов. Сначала модель коротко решает,
                    предложение ли это услуги. Полный разбор карточки запускается только для таких сообщений.
                    Если карточку разобрать не удалось, строка всё равно остаётся в таблице с пометкой ошибки
                    извлечения. Исходные посты и каталог не меняются.
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="{{ $startUrl }}"
                          onsubmit="return confirm('Таблица результатов будет очищена. Запустить новый тест на 300 сообщениях?');">
                        @csrf
                        <button type="submit" class="btn btn-primary" @disabled($isActive)>
                            {{ $isActive ? 'Тест выполняется' : 'Запустить тест' }}
                        </button>
                    </form>
                    @if($isActive)
                        <form method="post" action="{{ $cancelUrl }}"
                              onsubmit="return confirm('Прервать текущий тест? Уже разобранные строки останутся.');">
                            @csrf
                            <button type="submit" class="btn btn-error" @disabled($isStopping)>
                                {{ $isStopping ? 'Останавливается' : 'Прервать тест' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if($error)
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ $error }}
                </div>
            @endif

            @if($run)
                <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-dark-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Статус</div>
                        <div class="font-semibold">{{ $isStopping ? 'Останавливается' : $run->status->label() }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-dark-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Обработано</div>
                        <div class="font-semibold">{{ $run->processed_count }} / {{ $run->sampled_count ?: $run->sample_size }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-dark-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Целевых</div>
                        <div class="text-2xl font-bold leading-none">{{ $run->target_count }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-dark-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Ошибок разбора</div>
                        <div class="font-semibold">{{ $run->error_count }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-dark-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Ошибок извлечения</div>
                        <div class="font-semibold">{{ $run->extraction_error_count }}</div>
                    </div>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <div>Модель: <strong>{{ $run->model ?: '—' }}</strong></div>
                    <div>Промпт: <strong>{{ $run->prompt_label ?: '—' }}</strong></div>
                    @if($run->heuristic_reject_count > 0)
                        <div>Снято эвристикой найма после ответа модели: <strong>{{ $run->heuristic_reject_count }}</strong></div>
                    @endif
                    @if($run->note)
                        <div>{{ $run->note }}</div>
                    @endif
                    @if($run->error_message)
                        <div class="text-red-700">{{ $run->error_message }}</div>
                    @endif
                    @if($run->last_error)
                        <div class="text-red-700 whitespace-pre-wrap break-words">Последняя ошибка: {{ $run->last_error }}</div>
                    @endif
                    @if($isStopping)
                        <div>Текущее сообщение ещё дочитывается, затем прогон остановится.</div>
                    @endif
                    @if($run->started_at)
                        <div>Старт: {{ $run->started_at->format('d.m.Y H:i:s') }}
                            @if($run->finished_at)
                                · окончание: {{ $run->finished_at->format('d.m.Y H:i:s') }}
                            @endif
                        </div>
                    @endif
                    @if($isActive)
                        <div>Страница обновляется сама, пока тест идёт.</div>
                    @endif
                </div>
            @else
                <div class="text-sm text-gray-500">Тест ещё не запускался.</div>
            @endif
        </div>
    </section>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-dark-600 bg-gray-50 dark:bg-dark-900 p-3">
        <table class="table-auto w-full text-sm border-separate" style="border-spacing: 0 0.75rem;">
            <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-3 pb-1 whitespace-nowrap">Дата</th>
                <th class="px-3 pb-1">Автор</th>
                <th class="px-3 pb-1">Канал</th>
                <th class="px-3 pb-1">Сообщение</th>
                <th class="px-3 pb-1">Решение модели</th>
            </tr>
            </thead>
            <tbody>
            @forelse($messages ?? [] as $row)
                <tr class="align-top bg-white dark:bg-dark-800">
                    <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                        {{ $row->post_date?->format('d.m.Y H:i') ?: '—' }}
                    </td>
                    <td class="px-3 py-3 min-w-40">
                        <div>{{ $row->author_name ?: 'без имени' }}</div>
                        @if($row->author_login)
                            <div class="text-gray-500">{{ '@'.$row->author_login }}</div>
                        @endif
                        @if($row->api_post_user_id)
                            <a class="text-primary text-xs" href="{{ $authorDetailUrl($row->api_post_user_id) }}">карточка автора</a>
                        @endif
                    </td>
                    <td class="px-3 py-3 min-w-40">{{ $row->channel_title ?: '—' }}</td>
                    <td class="px-3 py-3" style="min-width: 28rem; max-width: 48rem;">
                        <div class="whitespace-pre-wrap break-words max-h-64 overflow-y-auto">{{ $row->post_text }}</div>
                        @if($row->api_channel_post_id)
                            <a class="text-primary text-xs" href="{{ $postFormUrl($row->api_channel_post_id) }}">исходный пост</a>
                        @endif
                    </td>
                    <td class="px-3 py-3 min-w-52">
                        <div class="font-medium">{{ $row->ai_type }}</div>
                        @if($row->extraction_error)
                            <div class="mt-1 text-red-700 whitespace-pre-wrap break-words">Ошибка извлечения: {{ $row->extraction_error }}</div>
                        @endif
                        @if($row->ai_reason)
                            <div class="mt-1 text-gray-600 whitespace-pre-wrap">{{ $row->ai_reason }}</div>
                        @endif
                        @if($row->specialities)
                            <div class="mt-2 text-xs text-gray-500">Специализации: {{ $row->specialities }}</div>
                        @endif
                        @if($row->model_reply)
                            <details class="mt-2 text-xs">
                                <summary class="cursor-pointer text-gray-500">Ответ модели</summary>
                                <pre class="mt-1 whitespace-pre-wrap break-words">{{ $row->model_reply }}</pre>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-6 text-center text-gray-500">
                        @if($isActive)
                            Целевых сообщений пока нет: модель ещё разбирает выборку.
                        @elseif($run)
                            Модель не отметила целевых сообщений в этой выборке.
                        @else
                            Здесь появятся сообщения, которые модель сочтёт целевыми.
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($messages && $messages->hasPages())
        <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Страницы теста">
            @if($messages->onFirstPage())
                <span class="px-3 py-2 rounded-md border border-gray-200 text-gray-400">‹</span>
            @else
                <a href="{{ $messages->previousPageUrl() }}" class="px-3 py-2 rounded-md border border-gray-200">‹</a>
            @endif
            @for($page = 1; $page <= $messages->lastPage(); $page++)
                @if($page === $messages->currentPage())
                    <span class="px-3 py-2 rounded-md border border-primary bg-primary text-white">{{ $page }}</span>
                @else
                    <a href="{{ $messages->url($page) }}" class="px-3 py-2 rounded-md border border-gray-200">{{ $page }}</a>
                @endif
            @endfor
            @if($messages->hasMorePages())
                <a href="{{ $messages->nextPageUrl() }}" class="px-3 py-2 rounded-md border border-gray-200">›</a>
            @else
                <span class="px-3 py-2 rounded-md border border-gray-200 text-gray-400">›</span>
            @endif
        </nav>
    @endif
</div>
