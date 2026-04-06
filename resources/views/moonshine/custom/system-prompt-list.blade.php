@php
    /** @var string $createUrl */
    /** @var string $applyTypeLabel */
    /** @var list<array{id:int,name:string,sourceLabel:string,editUrl:string,applyButtonHtml:string}> $rows */
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Сохранённые варианты системного промпта. «Применить» копирует текст в «Промт для ИИ» у источников с типом выборки «{{ $applyTypeLabel }}»: для строки «Все ИИ обработчики» — у всех таких источников; для конкретного провайдера — только у каналов с этим сервисом ИИ. Зелёная кнопка «Применить» — сохранённый текст совпадает у всех подходящих источников; синяя — иначе.
        </p>
        <a href="{{ $createUrl }}" class="btn btn-primary">Добавить промпт</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-slate-700">
        <table class="w-full border-collapse text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/80">
            <tr>
                <th class="border-b border-gray-200 px-4 py-3 text-left font-medium dark:border-slate-600">Название</th>
                <th class="border-b border-gray-200 px-4 py-3 text-left font-medium dark:border-slate-600">Обработчик ИИ</th>
                <th class="w-48 border-b border-gray-200 px-4 py-3 text-left font-medium dark:border-slate-600"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                    <td class="border-b border-gray-100 px-4 py-3 dark:border-slate-700">{{ $row['name'] }}</td>
                    <td class="border-b border-gray-100 px-4 py-3 dark:border-slate-700">{{ $row['sourceLabel'] }}</td>
                    <td class="space-x-2 whitespace-nowrap border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                        <a href="{{ $row['editUrl'] }}" class="btn btn-sm btn-secondary">Изменить</a>
                        <input type="hidden" id="apply-preset-{{ $row['id'] }}" value="{{ $row['id'] }}">
                        {!! $row['applyButtonHtml'] !!}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-center text-slate-500">Нет сохранённых промптов. Нажмите «Добавить промпт».</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
