@php
    /** @var string $initialBody */
    /** @var string $textareaId */
    /** @var string $saveButtonHtml */
    /** @var string $applyButtonHtml */
    /** @var string $applyTypeLabel */
@endphp

<div
    class="space-y-4"
    x-data="{
        content: @js($initialBody),
        saved: @js($initialBody),
        pendingUrl: '',
        leaveModal: false,
        get dirty() { return this.content !== this.saved; },
        confirmLeave() {
            if (this.pendingUrl) {
                window.location.href = this.pendingUrl;
            }
            this.leaveModal = false;
        },
    }"
    x-init="
        const root = $data;
        window.addEventListener('beforeunload', function (e) {
            if (root.dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
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
    <div>
        <label for="{{ $textareaId }}" class="form-label">Системный промпт</label>
        <x-moonshine::form.textarea
            :attributes="new \Illuminate\View\ComponentAttributeBag([
                'id' => $textareaId,
                'name' => 'ai_promt',
                'rows' => 18,
                'x-model' => 'content',
            ])"
        />
        <p class="form-hint mt-2">
            «Применить» копирует текущий текст в поле «Промт для ИИ» у всех источников сообщений с типом выборки
            «{{ $applyTypeLabel }}»
            . «Сохранить» записывает текст на этой странице; «Отмена» сбрасывает несохранённые правки (перезагрузка).
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        {!! $saveButtonHtml !!}
        {!! $applyButtonHtml !!}
        <x-moonshine::form.button
            class="btn-secondary"
            @click.prevent="window.location.reload()"
        >Отмена</x-moonshine::form.button>
    </div>

    <div
        x-show="leaveModal"
        x-cloak
        class="fixed inset-0 z-[300] flex items-center justify-center bg-black/50 px-4"
        style="display: none;"
        @keydown.escape.window="leaveModal = false"
    >
        <div
            class="modal-content max-w-lg rounded-lg bg-white p-6 shadow-lg dark:bg-slate-900"
            @click.outside="leaveModal = false"
        >
            <p class="text-sm text-slate-800 dark:text-slate-100">
                Внимание! Сделанные изменения не будут сохранены
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <x-moonshine::form.button class="btn-secondary" @click.prevent="leaveModal = false">
                    Отмена
                </x-moonshine::form.button>
                <x-moonshine::form.button class="btn-primary" @click.prevent="confirmLeave()">
                    ОК
                </x-moonshine::form.button>
            </div>
        </div>
    </div>
</div>
