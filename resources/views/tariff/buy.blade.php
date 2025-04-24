<x-global-layout>
    <div class="container my-5">
        <h1 class="text-body-emphasis text-center">Оформление тарифа "{{ $tariff->title }}"</h1>

        <p>{{ $tariff->description }}</p>
        <p>Количество месяцев: {{ $tariff->period }}</p>
        <p>Количество контактов: {{ $tariff->count_contacts }}</p>

        @if (!Auth::user())
            <div class="alert alert-warning">
                <p>Для оформления тарифа сначала нужно авторизоваться или зарегистрироваться.</p>
            </div>
        @endif

        <a href="#" class="btn btn-primary @if (!Auth::user()) disabled @endif">Оплатить</a>
    </div>
</x-global-layout>
