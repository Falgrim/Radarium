<x-global-layout>
    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <section>
                    @if(count($subscribeActive))
                        <h3>Активная подписка</h3>
                        @foreach($subscribeActive as $row)
                            <div class="col">
                                <div class="card mb-4 rounded-3 shadow-sm ">
                                    <div class="card-header py-3 @if($row->status === \App\Enum\UserTariffStatusEnum::Disabled OR $row->status === \App\Enum\UserTariffStatusEnum::Ended) text-bg-warning border-warning @endif">
                                        <h4 class="my-0 fw-normal text-center">{{ $row->paymentTariff->title }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p>Текущий статус: <b>{{ $row->status->toString() }}</b></p>
                                        <p>{{ $row->paymentTariff->description }}</p>
                                        <p>С {{ $row->date_start->format('H:i d.m.Y') }} до {{ $row->date_end->format('H:i d.m.Y') }} (осталось {{ $row->date_start->diffInDays($row->date_end) }} дн.)</p>
                                        <p>Доступно контактов: {{ $row->count_contacts_left }} из {{ $row->count_contacts }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ __('Ваша подписка') }}
                        </h3>
                        @if(Auth::user()->free_contacts)
                            <p>У вас сейчас активирован Демо режим. <a href="{{ route('index') }}#buy_tariff">Выберите тариф для работы с сервисом {{ config('app.name') }}</a></p>
                        @else
                            <p>У вас еще нет подписки.</p>
                        @endif
                    @endif

                    @if(count($subscribeEnded))
                        <h3>Завершенные подписки</h3>
                        @foreach($subscribeEnded as $row)
                            <div class="col">
                                <div class="card mb-4 rounded-3 shadow-sm ">
                                    <div class="card-header py-1 @if($row->status === \App\Enum\UserTariffStatusEnum::Disabled OR $row->status === \App\Enum\UserTariffStatusEnum::Ended) text-bg-warning border-warning @endif">
                                        <h4 class="my-0 fw-normal text-center">{{ $row->paymentTariff->title }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p>Текущий статус: <b>{{ $row->status->toString() }}</b></p>
                                        <p>{{ $row->paymentTariff->description }}</p>
                                        <p>С {{ $row->date_start->format('H:i d.m.Y') }} до {{ $row->date_end->format('H:i d.m.Y') }} (осталось {{ $row->date_start->diffInDays($row->date_end) }} дн.)</p>
                                        <p>Доступно контактов: {{ $row->count_contacts_left }} из {{ $row->count_contacts }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if(count($payments))
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ __('Платежи, ожидающие оплаты') }}
                        </h3>
                        <p>
                            <i>Если вы передумали оплачивать, то платежи автоматически будут отменены.</i><br />
                            <i>После оплаты, в течение нескольких минут у вас будет активирован оплаченный тарифный план.</i>
                        </p>
                        @foreach($payments as $row)
                            <div class="col py-3">
                                <p>Тариф "{{ $row->paymentTariff->title }}". Создан платеж {{ $row->created_at->format('H:i d.m.Y') }} на сумму {{ number_format($row->sum, 0, '.', ' ') }} руб.</p>
                                <p><a href="https://auth.robokassa.ru/Merchant/Index/{{ $row->payment_hash }}" target="_blank" class="btn btn-primary btn-sm">Оплатить</a></p>
                                <hr />
                            </div>
                        @endforeach
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-global-layout>
