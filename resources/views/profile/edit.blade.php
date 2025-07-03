<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Главная</span></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('catalog.specialists') }}"><span>Поиск</span></a></li>
                        <li class="breadcrumb-item active"><span>Личный кабинет</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="container pers-account">
            <div class="row">
                <div class="col">
                    <div class="div2cols">
                        <div class="box-simple">
                            <h3 class="box-heading c-accent">{{ __('Данные пользователя') }}</h3>
                            <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                                @csrf
                            </form>
                            <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
                                @csrf
                                @method('patch')

                                <div class="registration-form on-light">
                                    <input class="form-control" type="text" placeholder="ФИО" id="name"  name="name" value="{{ old('name', $user->name) }}" required="required">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="from_company" name="from_company" value="1" @if($user->company_title OR $user->company_inn) checked @endif >
                                        <label class="form-check-label" for="from_company">Представляю компанию</label>
                                    </div>
                                    <input class="form-control" type="text" name="company_title" value="{{ old('company_title', $user->company_title) }}" placeholder="Название компании">
                                    <input class="form-control" type="text" name="company_inn" value="{{ old('company_inn', $user->company_inn) }}" placeholder="ИНН компании">
                                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="Почта" required="required">
                                    <div class="box-bttn" style="margin-top: auto;">
                                        <button class="btn btn-normal btn-color" type="submit">Сохранить изменения</button>
                                    </div>
                                </div>
                            </form>
                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                                <div>
                                    <p class="text-sm mt-2 text-gray-800">
                                        {{ __('Ваша почта не подтверждена.') }}

                                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            {{ __('Нажмите для повторной отправки письма для подтверждения.') }}
                                        </button>
                                    </p>

                                    @if (session('status') === 'verification-link-sent')
                                        <p class="mt-2 font-medium text-sm text-green-600">
                                            {{ __('Новая ссылка для подтверждения была выслана на вашу почту.') }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="box-simple">
                            <h3 class="box-heading c-blue">Изменения пароля</h3>
                            <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
                                @csrf
                                @method('put')
                                <div class="registration-form on-light">
                                    <p>Укажите сложный пароль с набором различных символов для максимальной защиты.</p>
                                    <input class="form-control" type="password" name="current_password" placeholder="Текущий пароль">
                                    <input class="form-control" type="password" name="password" placeholder="Новый пароль">
                                    <input class="form-control" type="password" name="password_confirmation" placeholder="Повторите новый пароль">
                                    <div class="box-bttn">
                                        <button class="btn btn-normal btn-color" type="submit">Сохранить изменения</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!count($subscribeActive))
        <div class="container select-subscription">
            <div class="row">
                <div class="col">
                    <div class="box-simple fd-row">
                        <div>
                            <h3>Ваши подписки</h3>
                            <p>У вас еще нет подписки.</p>
                        </div><a class="btn btn-color btn-accent btn-small" role="button" href="{{ route('index') }}#tarifs">Выбрать тариф</a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(count($payments) OR count($subscribeActive) OR count($subscribeEnded))
        <div class="container pers-subscription">
            <div class="table-box box-simple">
                <div class="table-heading">
                    <h3>Ваши подписки</h3>
                </div>
                <div class="table-responsive text-start">
                    <table class="table table-borderless tbl-default tbl-subscriptions">
                        <thead>
                        <tr>
                            <th class="def-cell-01">Тариф</th>
                            <th class="def-cell-02">Статус</th>
                            <th class="def-cell-03">Период</th>
                            <th class="def-cell-04">Доступно</th>
                            <th class="def-cell-05">Цена</th>
                            <th class="def-cell-06">&nbsp;</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if(count($payments))
                            @foreach($payments as $row)
                                <tr>
                                    <td class="def-cell-01"><strong>{{ $row->paymentTariff->title }}</strong></td>
                                    <td class="def-cell-02">
                                    <span>
                                        <img class="img-fluid img-status" width="16" height="17" src="{{ asset('v2/img/circle-blue.png') }}">Ожидает оплаты
                                    </span>
                                    </td>
                                    <td class="def-cell-03">
                                        <p>{{ Carbon\Carbon::now()->format('d.m.Y') }} – {{ Carbon\Carbon::now()->addDays($row->paymentTariff->period)->format('d.m.Y') }}</p>
                                        <p>Осталось<strong class="b-number">{{ $row->paymentTariff->period }}</strong><strong class="b-days">дн.</strong></p>
                                    </td>
                                    <td class="def-cell-04">
                                        <p><strong>-</strong></p>
                                    </td>
                                    <td class="def-cell-05">
                                        <p><strong>{{ number_format($row->paymentTariff->price, 0, '.', ' ') }}&nbsp;<i class="fas fa-ruble-sign"></i></strong></p>
                                    </td>
                                    <td class="def-cell-06">
                                        <a href="https://auth.robokassa.ru/Merchant/Index/{{ $row->payment_hash }}" class="btn w-100 btn-color btn-dgrey btn-small">Ожидает оплаты</a>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                        @if(count($subscribeActive))
                            @foreach($subscribeActive as $row)
                            <tr>
                                <td class="def-cell-01"><strong>{{ $row->paymentTariff->title }}</strong></td>
                                <td class="def-cell-02">
                                    <span>
                                        <img class="img-fluid img-status" width="16" height="17" src="{{ asset('v2/img/circle-green.png') }}">Активен
                                    </span>
                                </td>
                                <td class="def-cell-03">
                                    <p>{{ $row->date_start->format('d.m.Y') }} – {{ $row->date_end->format('d.m.Y') }}</p>
                                    <p>Осталось<strong class="b-number">{{ $row->date_start->diffInDays($row->date_end) }}</strong><strong class="b-days">дн.</strong></p>
                                </td>
                                <td class="def-cell-04">
                                    <p><strong>{{ $row->count_contacts_left }}</strong>/<strong>{{ $row->count_contacts }}</strong></p>
                                </td>
                                <td class="def-cell-05">
                                    <p><strong>{{ number_format($row->paymentTariff->price, 0, '.', ' ') }}&nbsp;<i class="fas fa-ruble-sign"></i></strong></p>
                                </td>
                                <td class="def-cell-06">
                                    <button class="btn btn-link w-100 btn-color btn-small" type="button">Оплачено</button>
                                </td>
                            </tr>
                            @endforeach
                        @endif

                        @if(count($subscribeEnded))
                            @foreach($subscribeEnded as $row)
                                <tr>
                                    <td class="def-cell-01"><strong>{{ $row->paymentTariff->title }}</strong></td>
                                    <td class="def-cell-02">
                                    <span>
                                        <img class="img-fluid img-status" width="16" height="17" src="{{ asset('v2/img/circle-red.png') }}">Закончился
                                    </span>
                                    </td>
                                    <td class="def-cell-03">
                                        <p>{{ $row->date_start->format('d.m.Y') }} – {{ $row->date_end->format('d.m.Y') }}</p>
                                        <p>Осталось<strong class="b-number">0</strong><strong class="b-days">дн.</strong></p>
                                    </td>
                                    <td class="def-cell-04">
                                        <p><strong>{{ $row->count_contacts_left }}</strong>/<strong>{{ $row->count_contacts }}</strong></p>
                                    </td>
                                    <td class="def-cell-05">
                                        <p><strong>{{ number_format($row->paymentTariff->price, 0, '.', ' ') }}&nbsp;<i class="fas fa-ruble-sign"></i></strong></p>
                                    </td>
                                    <td class="def-cell-06">
                                        <button class="btn btn-link w-100 btn-color btn-small" type="button">Оплачено</button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <x-footer-finish />
    </main>
</x-global-layout>
