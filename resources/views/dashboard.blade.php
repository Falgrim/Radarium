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

                @if(Auth::user()->free_contacts)
                    <p>Спасибо за регистрацию в сервисе Radarium. Сейчас вам доступен демонстрационный режим. Вы можете видеть базу данных объявлений специалистов, но Вы можете открыть только три карточки специалистов для ознакомления с возможностями сервиса. Для получения доступа к контактным данным выберите тариф <a href="{{ route('index') }}#buy_tariff">здесь</a>.</p>
                @endif
            </div>
        </div>
    </div>
</x-global-layout>
