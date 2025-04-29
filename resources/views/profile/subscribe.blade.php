<x-global-layout>
    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-5">
                <section>
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ __('Ваша подписка') }}
                    </h2>

                    @if(count($subscribe))
                        @foreach($subscribe as $row)
                            <div class="col">
                                <div class="card mb-4 rounded-3 shadow-sm ">
                                    <div class="card-header py-3 @if($row->status === \App\Enum\UserTariffStatusEnum::Disabled->value OR $row->status === \App\Enum\UserTariffStatusEnum::Ended->value) text-bg-warning border-warning @endif">
                                        <h4 class="my-0 fw-normal text-center">{{ $row->paymentTariff->title }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p>{{ $row->paymentTariff->description }}</p>
                                        <p>С {{ $row->date_start->format('H:i d.m.Y') }} до {{ $row->date_end->format('H:i d.m.Y') }} (осталось {{ $row->date_start->diffInDays($row->date_end) }} дн.)</p>
                                        <p>Доступно контактов: {{ $row->count_contacts_left }} из {{ $row->count_contacts }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p>У вас еще нет подписки.</p>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-global-layout>
