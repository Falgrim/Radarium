<x-global-layout>
    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <h1 class="text-body-emphasis">{{ __("Вы авторизованы!") }}</h1>
                @if (app('request')->input('verified'))
                    <p>
                        {{__("Регистрация в сервисе Radarium подтверждена")}}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-global-layout>
