@php
    /** @var string $listUrl */
    /** @var int|null $presetId */
    /** @var string $initialName */
    /** @var string $initialSource */
    /** @var string $initialBody */
    /** @var array<string, string> $sourceOptions */
    /** @var string $textareaId */
    /** @var string $saveButtonHtml */
    /** @var string $applyTypeLabel */
    $nameId = str_replace('-text', '-name', $textareaId);
    $sourceId = str_replace('-text', '-api-source', $textareaId);
    $presetHiddenId = str_replace('-text', '-preset-id', $textareaId);
@endphp

<div
    class="space-y-4"
    x-data="{
        name: @js($initialName),
        savedName: @js($initialName),
        source: @js($initialSource),
        savedSource: @js($initialSource),
        content: @js($initialBody),
        saved: @js($initialBody),
        pendingUrl: '',
        leaveModal: false,
        get dirty() {
            return this.content !== this.saved || this.name !== this.savedName || this.source !== this.savedSource;
        },
        confirmLeave() {
            if (this.pendingUrl) {
                window.location.href = this.pendingUrl;
            }
            this.leaveModal = false;
        },
    }"
    x-init="
        const root = $data;
        document.addEventListener('click', function (e) {
            if (!root.dirty) {
                return;
            }
            const a = e.target.closest('a');
            if (!a) {
                return;
            }
            const href = a.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript:')) {
                return;
            }
            if (href.includes('async/method')) {
                return;
            }
            let url;
            try {
                url = new URL(a.href, window.location.href);
            } catch (err) {
                return;
            }
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash === window.location.hash) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            root.pendingUrl = a.href;
            root.leaveModal = true;
        }, true);
    "
>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $listUrl }}" class="btn btn-secondary">← К списку промптов</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="{{ $nameId }}" class="form-label">Название промпта</label>
            <input
                type="text"
                id="{{ $nameId }}"
                name="name"
                class="form-input"
                maxlength="255"
                x-model="name"
            />
        </div>
        <div>
            <label for="{{ $sourceId }}" class="form-label">Обработчик ИИ (модель)</label>
            <select
                id="{{ $sourceId }}"
                name="api_source"
                class="form-select"
                x-model="source"
            >
                @foreach ($sourceOptions as $value => $label)
                    <option value="{{ $value }}" @selected($value === $initialSource)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="form-hint mt-1">По умолчанию — «Все ИИ обработчики»: при применении промпт запишется во все источники этой области без учёта выбранного сервиса ИИ. Остальные пункты — только активные «Сервисы ИИ» (если нет активных, показываются все поддерживаемые провайдеры).</p>
        </div>
    </div>

    @if ($presetId !== null)
        <input type="hidden" id="{{ $presetHiddenId }}" name="preset_id" value="{{ $presetId }}">
    @endif

    <div>
        <label for="{{ $textareaId }}" class="form-label">Текст системного промпта</label>
        <textarea
            id="{{ $textareaId }}"
            name="ai_promt"
            rows="18"
            class="form-textarea"
            x-model="content"
        ></textarea>
        <p class="form-hint mt-2">
            «Сохранить» записывает этот вариант в списке. «Применить» в списке копирует сохранённый текст: при «Все ИИ обработчики» — во все источники типа «{{ $applyTypeLabel }}»; при выборе одного провайдера — только в каналы с этим ИИ. «Отмена» сбрасывает несохранённые правки (перезагрузка страницы).
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        {!! $saveButtonHtml !!}
        <x-moonshine::form.button
            class="btn-secondary"
            x-on:click.prevent="window.location.reload()"
        >Отмена</x-moonshine::form.button>
    </div>

    <div
        x-show="leaveModal"
        x-cloak
        class="fixed inset-0 z-[300] flex items-center justify-center bg-black/50 px-4"
        style="display: none;"
        x-on:keydown.escape.window="leaveModal = false"
    >
        <div
            class="modal-content max-w-lg rounded-lg bg-white p-6 shadow-lg dark:bg-slate-900"
            x-on:click.outside="leaveModal = false"
        >
            <p class="text-sm text-slate-800 dark:text-slate-100">
                Внимание! Сделанные изменения не будут сохранены
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <x-moonshine::form.button class="btn-secondary" x-on:click.prevent="leaveModal = false">
                    Отмена
                </x-moonshine::form.button>
                <x-moonshine::form.button class="btn-primary" x-on:click.prevent="confirmLeave()">
                    ОК
                </x-moonshine::form.button>
            </div>
        </div>
    </div>
</div>
