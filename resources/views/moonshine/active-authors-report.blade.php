@php
    use App\Enum\ActiveAuthorsReportTypeEnum;
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var \Illuminate\Support\Collection<int, array{user: \App\Models\ApiPostUser, row: ?\App\Support\Admin\ActiveAuthorsReportRow}> $entries */
@endphp

<div class="space-y-8 p-4">
    <section class="rounded-xl border border-gray-200 dark:border-dark-600 bg-white dark:bg-dark-800 shadow-sm overflow-hidden">
        <div class="px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-5xl rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-950 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-100">
                    В этот отчет попадают <strong>уникальные авторы</strong> (аккаунты Telegram), у которых есть хотя бы одна
                    <strong>сейчас активная</strong> запись выбранного типа. Дата публикации связанного сообщения учитывается по выбранному периоду.
                    Сортировка: по дате последнего сообщения, новые сверху.
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-dark-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">TOTAL</div>
                    <div class="text-2xl font-bold leading-none">{{ $paginator->total() }}</div>
                </div>
            </div>

            <form method="get" action="{{ $reportFormAction }}" class="mt-4">
                <div style="display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
                    <div>
                        <label class="block text-sm font-medium mb-1">Тип записи</label>
                        <select name="type" class="form-select w-full rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
                            @foreach(ActiveAuthorsReportTypeEnum::cases() as $case)
                                <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Дата с</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="form-input w-full rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Дата по</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="form-input w-full rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
                    </div>
                </div>
                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 dark:border-dark-600 dark:bg-dark-900" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="multi_only" value="1" @checked($multiOnly)
                               class="rounded border-gray-300 dark:border-dark-500">
                        <span>Только авторы с <strong>2+</strong> активными карточками</span>
                    </label>
                    <div style="display: flex; gap: .75rem; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">Показать</button>
                        <a href="{{ $exportUrl }}" class="btn btn-secondary">Скачать CSV</a>
                    </div>
                </div>
            </form>

            <div class="mt-4 flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
                <div>
                    Найдено авторов: <strong>{{ $paginator->total() }}</strong>
                </div>
                <div>
                    @if($dateFrom || $dateTo)
                        Период: <strong>{{ $dateFrom?->format('d.m.Y') ?? '…' }} — {{ $dateTo?->format('d.m.Y') ?? '…' }}</strong>
                    @else
                        Период: <strong>за всё время</strong>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-dark-600 bg-gray-50 dark:bg-dark-900 p-3">
        <table class="table-auto w-full text-sm border-separate" style="border-spacing: 0 0.75rem;">
            <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 pb-1">Автор</th>
                <th class="px-4 pb-1 text-center whitespace-nowrap">Карточки</th>
                <th class="px-4 pb-1">Последнее сообщение</th>
                <th class="px-4 pb-1 whitespace-nowrap">Даты</th>
                <th class="px-4 pb-1">Статусы</th>
                <th class="px-4 pb-1 w-56">Действия</th>
            </tr>
            </thead>
            <tbody>
            @forelse($entries as $entry)
                @php
                    $user = $entry['user'];
                    $row = $entry['row'];
                @endphp
                @if($row)
                    @php
                        $cellBgClass = $row->postAiNotComplete
                            ? 'bg-amber-50 dark:bg-amber-950/25'
                            : 'bg-white dark:bg-dark-800';
                    @endphp
                    <tr class="align-top shadow-sm">
                        <td class="px-4 py-4 whitespace-nowrap rounded-l-lg {{ $cellBgClass }}">
                            @if($user->username)
                                <a href="https://t.me/{{ $user->username }}" target="_blank" rel="noopener" class="text-primary font-semibold">{{ '@' . $user->username }}</a>
                                @if($user->user_id)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Telegram ID: {{ $user->user_id }}</div>
                                @endif
                            @elseif($user->user_id)
                                <span class="font-semibold text-gray-700 dark:text-gray-200">id:{{ $user->user_id }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-center {{ $cellBgClass }}">
                            <span class="inline-flex min-w-9 items-center justify-center rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                {{ $row->activeCardsCount }}
                            </span>
                        </td>
                        <td class="px-4 py-4 min-w-80 max-w-xl break-words {{ $cellBgClass }}">
                            <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $row->lastMessage }}
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-600 dark:text-gray-300 {{ $cellBgClass }}">
                            <div><span class="text-gray-500 dark:text-gray-400">Пост:</span> {{ $row->lastMessagePostDate?->format('d.m.Y H:i') ?? '—' }}</div>
                            <div class="mt-1"><span class="text-gray-500 dark:text-gray-400">ИИ:</span> {{ $row->domainCreatedAt?->format('d.m.Y H:i') ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-4 text-xs {{ $cellBgClass }}">
                            <div><span class="text-gray-500 dark:text-gray-400">Запись:</span> <span class="font-semibold">{{ $row->domainStatusLabel }}</span></div>
                            <div class="mt-1"><span class="text-gray-500 dark:text-gray-400">Сообщение:</span> <span class="font-semibold">{{ $row->postAiStatusLabel }}</span></div>
                            @if($row->postAiNotComplete)
                                <div class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                    Требует внимания
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-4 rounded-r-lg {{ $cellBgClass }}">
                            <div class="grid gap-2">
                                <a href="{{ $authorDetailUrl($user->id) }}" class="btn btn-sm btn-primary w-full text-center">Открыть автора</a>
                                <a href="{{ route('admin.active-authors-report.edit', ['type' => $row->type->value, 'domain_id' => $row->domainId]) }}"
                                   class="btn btn-sm btn-secondary w-full text-center">Редактировать статусы</a>
                                @if($row->postId)
                                    <a href="{{ $postFormUrl($row->postId) }}" class="btn btn-sm btn-secondary w-full text-center" target="_blank">Открыть сообщение</a>
                                @endif
                                <a href="{{ $domainFormUrl($row->type, $row->domainId) }}" class="btn btn-sm btn-secondary w-full text-center" target="_blank">Открыть запись</a>

                                <details class="mt-1 border-t border-red-100 pt-2 dark:border-red-900/40">
                                    <summary class="cursor-pointer text-xs font-semibold text-red-600 dark:text-red-300">Удаление</summary>
                                    <form method="post" action="{{ $deleteUrl }}" class="mt-2 space-y-2"
                                          onsubmit="const scope=this.delete_scope.value;const label=scope==='active_only'?'только активные записи типа «{{ $type->label() }}»':'все записи типа «{{ $type->label() }}»';return confirm('Удалить (мягкое удаление): '+label+' для этого автора?');">
                                        @csrf
                                        <input type="hidden" name="return_query" value="{{ e(request()->getQueryString() ?? '') }}">
                                        <input type="hidden" name="type" value="{{ $row->type->value }}">
                                        <input type="hidden" name="api_post_user_id" value="{{ $user->id }}">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Объём удаления</label>
                                            <select name="delete_scope" class="form-select form-select-sm w-full rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900 text-xs">
                                                <option value="active_only">Только активные записи типа</option>
                                                <option value="all">Все записи типа (включая неактивные)</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-error w-full">Удалить записи</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">Нет данных по заданным критериям.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-2 text-center">
        @if($paginator->hasPages())
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Пагинация отчёта">
                @if($paginator->onFirstPage())
                    <span class="px-3 py-2 rounded-md border border-gray-200 dark:border-dark-600 text-gray-400 cursor-not-allowed">‹</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-2 rounded-md border border-gray-200 dark:border-dark-600 hover:bg-gray-50 dark:hover:bg-dark-700">‹</a>
                @endif

                @for($page = 1; $page <= $paginator->lastPage(); $page++)
                    @if($page === $paginator->currentPage())
                        <span class="px-3 py-2 rounded-md border border-primary bg-primary text-white" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="px-3 py-2 rounded-md border border-gray-200 dark:border-dark-600 hover:bg-gray-50 dark:hover:bg-dark-700">{{ $page }}</a>
                    @endif
                @endfor

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-2 rounded-md border border-gray-200 dark:border-dark-600 hover:bg-gray-50 dark:hover:bg-dark-700">›</a>
                @else
                    <span class="px-3 py-2 rounded-md border border-gray-200 dark:border-dark-600 text-gray-400 cursor-not-allowed">›</span>
                @endif
            </nav>
        @endif

        <div class="text-sm text-gray-600 dark:text-gray-400">
            TOTAL: <strong>{{ $paginator->total() }}</strong>
        </div>
    </div>
</div>
