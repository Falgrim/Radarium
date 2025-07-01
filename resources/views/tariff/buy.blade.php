<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item active"><span>Оформление тарифа</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="container pers-account">
            <div class="row">
                <div class="col">
                    <div class="div2cols">
                        <div class="box-simple">
                            <h3 class="box-heading c-blue">Оформление тарифа "{{ $tariff->title }}"</h3>

                            <div class="registration-form on-light">
                                <p>{{ $tariff->description }}</p>
                                <p>Количество дней: {{ $tariff->period }}</p>
                                <p>Количество контактов: {{ $tariff->count_contacts }}</p>

                                @if (!Auth::user())
                                    <div class="alert alert-warning">
                                        <p>Для оформления тарифа сначала нужно авторизоваться или зарегистрироваться.</p>
                                    </div>
                                @else
                                    <div class="box-bttn" style="margin-top: auto;">
                                        <a href="{{ $paymentLink }}" target="_blank" class="btn btn-normal btn-color" type="submit">{{ __('Оплатить') }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-footer-finish />
    </main>
</x-global-layout>
