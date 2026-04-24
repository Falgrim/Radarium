@php
    use App\Enum\ActiveAuthorsReportTypeEnum;
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var \Illuminate\Support\Collection<int, array{user: \App\Models\ApiPostUser, row: ?\App\Support\Admin\ActiveAuthorsReportRow}> $entries */
@endphp

<div class="space-y-6 p-4">
    <div class="rounded-lg border border-gray-200 dark:border-dark-600 bg-white dark:bg-dark-800 p-4 shadow-sm">
        <h2 class="text-lg font-semibold mb-2">Критерии выборки</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            В список попадают <strong>уникальные авторы</strong> (аккаунты Telegram), у которых есть хотя бы одна
            <strong>сейчас активная</strong> запись выбранного типа, при этом дата публикации связанного сообщения попадает в выбранный период
            (если период не задан — за всё время). Сортировка: по дате последнего сообщения (новые сверху).
        </p>
        <form method="get" action="{{ $reportFormAction }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium mb-1">Тип записи</label>
                <select name="type" class="form-select rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
                    @foreach(ActiveAuthorsReportTypeEnum::cases() as $case)
                        <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Дата с</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="form-input rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Дата по</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="form-input rounded-md border-gray-300 dark:border-dark-500 dark:bg-dark-900">
            </div>
            <div class="flex items-center pb-1">
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="multi_only" value="1" @checked($multiOnly)
                           class="rounded border-gray-300 dark:border-dark-500">
                    <span>Только авторы с 2+ активными карточками</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Показать</button>
            <a href="{{ $exportUrl }}" class="btn btn-secondary">Скачать CSV</a>
        </form>
    </div>

    <div class="text-sm text-gray-600 dark:text-gray-400">
        Найдено авторов: <strong>{{ $paginator->total() }}</strong>
        @if($dateFrom || $dateTo)
            <span class="ml-2">(период: {{ $dateFrom?->format('d.m.Y') ?? '…' }} — {{ $dateTo?->format('d.m.Y') ?? '…' }})</span>
        @else
            <span class="ml-2">(за всё время)</span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-dark-600">
        <table class="table-auto w-full text-sm">
            <thead class="bg-gray-50 dark:bg-dark-700 text-left">
            <tr>
                <th class="p-3">Логин</th>
                <th class="p-3 whitespace-nowrap">Активных карточек</th>
                <th class="p-3">Последнее сообщение</th>
                <th class="p-3">Дата публикации</th>
                <th class="p-3">Создана запись ИИ</th>
                <th class="p-3">Статусы ИИ</th>
                <th class="p-3 w-56">Действия</th>
            </tr>
            </thead>
            <tbody>
            @forelse($entries as $entry)
                @php
                    $user = $entry['user'];
                    $row = $entry['row'];
                @endphp
                @if($row)
                    <tr @class([
                        'border-t border-gray-200 dark:border-dark-600 align-top',
                        'bg-amber-50 dark:bg-amber-950/25' => $row->postAiNotComplete,
                    ])>
                        <td class="p-3 whitespace-nowrap">
                            @if($user->username)
                                <a href="https://t.me/{{ $user->username }}" target="_blank" rel="noopener" class="text-primary">{{ '@' . $user->username }}</a>
                            @elseif($user->user_id)
                                <span class="text-gray-500">id:{{ $user->user_id }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="p-3 whitespace-nowrap text-center">{{ $row->activeCardsCount }}</td>
                        <td class="p-3 max-w-md break-words">{{ $row->lastMessage }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $row->lastMessagePostDate?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $row->domainCreatedAt?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="p-3 text-xs">
                            <div><span class="text-gray-500">Запись:</span> {{ $row->domainStatusLabel }}</div>
                            <div><span class="text-gray-500">Сообщение:</span> {{ $row->postAiStatusLabel }}</div>
                        </td>
                        <td class="p-3 space-y-1">
                            <a href="{{ $authorDetailUrl($user->id) }}" class="btn btn-sm btn-primary w-full text-center">Автор</a>
                            <a href="{{ route('admin.active-authors-report.edit', ['type' => $row->type->value, 'domain_id' => $row->domainId]) }}"
                               class="btn btn-sm btn-secondary w-full text-center">Статусы ИИ</a>
                            @if($row->postId)
                                <a href="{{ $postFormUrl($row->postId) }}" class="btn btn-sm btn-secondary w-full text-center" target="_blank">Сообщение</a>
                            @endif
                            <a href="{{ $domainFormUrl($row->type, $row->domainId) }}" class="btn btn-sm btn-secondary w-full text-center" target="_blank">Запись в разделе</a>
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
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">Нет данных по заданным критериям.</td>
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
