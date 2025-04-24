<x-landing-layout>
    <div class="container-lg p-5 mb-4">
        <div class="d-flex flex-lg-row gap-3 align-items-start align-items-lg-center py-3 link-body-emphasis text-decoration-none">
            <div class="col-6">
                <h1 class="mb-5">Исполнители</h1>
                <p>Получайте систематизированные данные о специалистах более чем с 50 профильных каналов в отрасли.</p>
                <p>Связывайтесь с ними в один клик.</p>
                <p>Оценивайте качество обращения и работу.</p>
                <p class="mt-5">Доступные отрасли:</p>
                <button type="button" class="btn btn-light">Проектирование</button>
            </div>
            <div class="col-6 landing_head_img">
                <img src="{{ asset('images/landing_head.jpg') }}" />
            </div>
        </div>
        <div class="col-lg-12 text-center mt-5">
            <a href="{{ route('login') }}" class="btn btn-dark">Войти</a>
            <a href="#" class="btn btn-secondary">Хочу запросить услугу новой отрасли</a>
        </div>
    </div>

    <div class="p-lg-5 p-1 mb-4 bg-body-tertiary rounded-3">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold text-center">Почему мы?</h1>
            <div class="row p-lg-5 p-0 landing_why">
                <div class="col-lg-6 col-md-12">
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Инновационность
                        </div>
                        <div class="col-6 landing_why_block">
                            Сервис основа на работе искусственного интеллекта
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Точность выборки
                        </div>
                        <div class="col-6 landing_why_block">
                            <span>99.8%</span> Мы сравниваем ручные и машинные показатели
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Удобство сборки
                        </div>
                        <div class="col-6 landing_why_block">
                            Специалисты отсортированы по направлениям и отраслям, мы выделили основные полезные<br />данные.
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            ИИ эффективнее
                        </div>
                        <div class="col-6 landing_why_block">
                            <span>1%</span> ИИ находит на 1% больше объявлений, чем человек
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12">
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Качество
                        </div>
                        <div class="col-6 landing_why_block">
                            Мы делаем контрольные проверки выборки вручную.
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Актуальность
                        </div>
                        <div class="col-6 landing_why_block">
                            <span>24</span> Сборка осуществляется ежедневно в круглосуточном режиме
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            Удобство оповещений
                        </div>
                        <div class="col-6 landing_why_block">
                            Вы можете настроить удобную для себя частоту уведомлений и периоды сборки, а также способ получения уведомлений.
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 landing_why_title">
                            ИИ быстрее
                        </div>
                        <div class="col-6 landing_why_block">
                            <span>1000</span> в такое количество раз ИИ тратит меньше времени на обработку
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-lg p-5 mb-4">
        <h2 class="display-5 fw-bold text-center">Стоимость подключения</h2>

        <div class="row row-cols-1 row-cols-md-3 mb-3 mt-5 landing_price">
            @foreach($tariffs as $tariff)
                <div class="col">
                    <div class="card mb-4 rounded-3 shadow-sm @if($tariff->is_hot) border-primary @endif">
                        <div class="card-header py-3 @if($tariff->is_hot) text-bg-primary border-primary @endif">
                            <h4 class="my-0 fw-normal text-center">{{ $tariff->title }}</h4>
                        </div>
                        <div class="card-body">
                            <p>{{ $tariff->description }}</p>
                            <h3 class="card-title pricing-card-title">{{ number_format($tariff->price, 0, '.', ' ') }} ₽ <small class="text-body-secondary fw-light">за {{ $tariff->period }} мес.</small></h3>
                            <h3 class="card-title pricing-card-title">{{ number_format($tariff->count_contacts, 0, '.', ' ') }} <small class="text-body-secondary fw-light"> контактов</small></h3>
                        </div>

                        <a href="{{ route('tariff.buy', ['id' => $tariff->id]) }}" class="w-100 btn btn-lg btn-outline-primary">Заказать</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="p-5 mb-4 bg-body-tertiary rounded-3">
        <div class="container-fluid py-5">
            <h2 class="display-5 fw-bold text-center">Часто задаваемые вопросы</h2>
        </div>
    </div>
</x-landing-layout>
