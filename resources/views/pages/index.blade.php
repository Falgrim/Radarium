<x-landing-layout>
    <main>
        <div class="container">
            <div class="herobox">
                <div>
                    <h1>Сервис для поиска специалистов и подрядчиков с помощью ИИ</h1>
                    <p class="rowline">
                        <button class="btn rowline-item btn-trans btn-light" type="button"><br># Проектирование<br><br></button>
                        <button class="btn rowline-item btn-trans btn-light" type="button"><br># Строительство МСК<br><br></button>
                    </p>
                </div>
                <div>
                    <h2>Radarium</h2>
                    <p class="rowline">Быстрый поиск профессионалов из более чем 100 профильных ТГ каналов</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="tricols">
                <div class="tricols-item"><img src="{{ asset('v2/img/icon-man.png') }}">
                    <p>Ищете узкоспециализированных исполнителей отрасли?</p>
                </div>
                <div class="tricols-item"><img src="{{ asset('v2/img/icon-wallet.svg') }}">
                    <p>Устали от&nbsp;высоких затрат на&nbsp;кадровиков и&nbsp;тендерных специалистов?</p>
                </div>
                <div class="tricols-item"><img src="{{ asset('v2/img/icon-time.svg') }}">
                    <p>Поиск нужных специалистов&nbsp; по&nbsp;открытым источникам занимает слишком много&nbsp;времени?</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="section-heading">
                <h2>Найдите специалиста<span>за 3 шага</span></h2>
            </div>
            <div class="tristeps-cols">
                <div class="tristeps-cols--item login-col">
                    <h3 class="title">Вход в систему</h3>
                    <p>Зарегистрируйтесь в&nbsp;системе.<br>Выберите и&nbsp;подключите подходящий&nbsp;тариф.</p>
                    <button class="btn btn-trans btn-light btn-white" data-bs-toggle="modal" data-bs-target="#rd-registr">Вход</button>
                    <button class="btn btn-trans btn-light" type="button" data-bs-toggle="modal" data-bs-target="#rd-account">Регистрация</button>
                </div>
                <div class="tristeps-cols--item drive-col">
                    <div class="drive-col--item">
                        <h3 class="title">3 шага поиска специалистa</h3>
                        <p class="line-number"><span>1</span>Выберите специализацию и&nbsp;нужные&nbsp;навыки</p>
                        <p class="line-number"><span>2</span>Смотрите список подходящих специалистов</p>
                        <p class="line-number"><span>3</span>Связывайтесь с теми, кто подходит лучше&nbsp;всего</p>
                        <button class="btn btn-color btn-grey plastic" type="button">Оставляйте комментарии и&nbsp;отзывы<img src="{{ asset('v2/img/icon-plastic.png') }}"></button>
                    </div>
                    <div class="drive-col--item dc-divider"><span></span></div>
                    <div class="drive-col--item">
                        <h3 class="title">Бесплатный доступ</h3>
                        <p>Вы получаете полный доступ ко всем ключевым функциям на ограниченное время</p>
                        <button class="btn btn-color btn-accent" type="button" data-bs-toggle="modal" data-bs-target="#rd-drive">Тест–Драйв</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="industries" class="section-heading">
                <h2>Отрасли</h2>
            </div>
            <div class="tricols depo">
                <div class="tricols-item depo-info">
                    <h3 class="title">Проектировщики РФ</h3>
                    <p>Специалисты и&nbsp;подрядчики в&nbsp;области проектирования</p>
                    <div class="varcols var50">
                        <div class="varcols-item">
                            <p class="specinfo accent"><span>{{ $contactSum }}</span>Общее количество специалистов</p>
                        </div>
                        <div class="varcols-item">
                            <p class="specinfo"><span>{{ $contactTodaySum }}</span>Новых объявлений добавлено за сутки</p>
                        </div>
                    </div>
                </div>
                <div class="tricols-item depo-info no-active">
                    <h3 class="title">Строители г.&nbsp;Москва</h3>
                    <p>Специалисты и&nbsp;подрядчики в&nbsp;области проектирования</p><span class="note">в разработке</span>
                </div>
                <div class="tricols-item depo-plus"><button class="btn" type="button"><span class="plus-circle">+</span></button>
                    <p>Нужна другая отрасль</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="faq" class="section-heading">
                <h2>Как работает Radarium?</h2>
            </div>
            <div class="row section-mb">
                <div class="col-12 col-xl-8">
                    <div class="carousel slide pds" data-bs-ride="false" id="carousel-1">
                        <div class="carousel-inner">
                            <div class="carousel-item active"><img class="w-100 d-block" src="{{ asset('v2/img/image001-carousel-1.jpg') }}"></div>
                            <div class="carousel-item"><img class="w-100 d-block" src="{{ asset('v2/img/image001-carousel-1.jpg') }}"></div>
                            <div class="carousel-item"><img class="w-100 d-block" src="{{ asset('v2/img/image001-carousel-1.jpg') }}"></div>
                        </div>
                        <div>
                            <a class="carousel-control-prev" href="#carousel-1" role="button" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"><i class="fas fa-chevron-left"></i></span>
                                <span class="visually-hidden">Previous</span>
                            </a>
                            <a class="carousel-control-next" href="#carousel-1" role="button" data-bs-slide="next">
                                <span class="carousel-control-next-icon"><i class="fas fa-chevron-right"></i></span>
                                <span class="visually-hidden">Next</span>
                            </a>
                        </div>
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#carousel-1" data-bs-slide-to="0" class="active"></button>
                            <button type="button" data-bs-target="#carousel-1" data-bs-slide-to="1"></button>
                            <button type="button" data-bs-target="#carousel-1" data-bs-slide-to="2"></button>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="box-incol">
                        <div class="box-incol--item box-blue">
                            <h4>Выберите специализацию и&nbsp;настройте фильтры</h4>
                            <p>Можно выбрать одну или несколько. Поиск покажет всех, у кого встречается хотя бы одна.</p>
                        </div>
                        <div class="box-incol--item">
                            <h4>Изучите подборку, найдите интересующих Вас кандидатов</h4>
                            <p>В подборке доступны основные данные, ключевые теги, а также фрагмент последнего сообщения.</p>
                        </div>
                        <div class="box-incol--item">
                            <h4>Откройте карточку специалиста</h4>
                            <p>В карточке специалиста представлены данные о&nbsp;специалисте и&nbsp;история объявлений. Свяжитесь со специалистами доступными способами.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="section-heading">
                <h2>Почему Radarium уникален?</h2>
            </div>
            <div class="row section-mb">
                <div class="col-12 col-xl-8 garden">
                    <div class="row">
                        <div class="col-12 col-xl-6">
                            <div class="box-simple">
                                <h3 class="title-40bold">Искусственный интеллект</h3>
                                <p>ИИ в основе Radarium анализирует и&nbsp;систематизирует данные моментально со&nbsp;снайперской точностью</p>
                            </div>
                        </div>
                        <div class="col-12 col-xl-6">
                            <div class="box-simple">
                                <h3 class="title-40bold">Удобство использования</h3>
                                <p>Умные уведомления и&nbsp;точный поиск – выбирайте частоту оповещений и&nbsp;находите специалистов по&nbsp;направлениям</p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="box-simple plastic-double">
                                <h3 class="title-60bold t-shadow-white">Точность отбора</h3>
                                <div class="tricols">
                                    <div class="tricols-item icon2line"><img class="img30" src="{{ asset('v2/img/check-green.png') }}">
                                        <p class="t-shadow-white"><strong>99.8% точность </strong>ИИ подбирает только<br>тех, кто Вам нужен</p>
                                    </div>
                                    <div class="tricols-item icon2line"><img class="img30" src="{{ asset('v2/img/check-green.png') }}">
                                        <p class="t-shadow-white"><strong>Ручной контроль</strong>Мы дополнительно вручную проверяем выборку, <br>чтобы избежать ошибок и&nbsp;повторений</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="box-incol">
                        <div class="box-incol--item box-blue">
                            <h3 class="title-60bold">Высокая скорость обработки<img class="plastic-gear" src="{{ asset('v2/img/plastic-gear.png') }}"></h3>
                            <ul class="icon-list">
                                <li><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" class="bi bi-lightning-charge-fill">
                                        <path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"></path>
                                    </svg><strong>1000 раз быстрее</strong> ИИ экономит ваше время, мгновенно анализируя данные.</li>
                                <li><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" class="bi bi-lightning-charge-fill">
                                        <path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"></path>
                                    </svg><strong>24/7 сбор информации</strong> Данные обновляются в реальном времени.</li>
                            </ul>
                        </div>
                        <div class="box-incol--item">
                            <p class="text-center txt-24bold">ИИ находит на 118% больше релевантных специалистов, чем&nbsp;человек</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="buy_tariff" class="section-heading">
                <h2>Тарифы</h2>
            </div>
            <div class="box-tarif">
                @foreach($tariffs as $tariff)
                    <div class="box-tarif--item @if($tariff->is_hot) box-blue @endif">
                        <div>
                            <p class="txt-30bold">
                            @if($tariff->is_hot) <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="#F16F44" viewBox="0 0 16 16" class="bi bi-lightning-charge">
                                <path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09zM4.157 8.5H7a.5.5 0 0 1 .478.647L6.11 13.59l5.732-6.09H9a.5.5 0 0 1-.478-.647L9.89 2.41z"></path>
                            </svg> @endif
                            {{ $tariff->title }}
                            </p>
                            <p>Выбранный период</p>
                        </div>
                        <div>
                            <p>{{ $tariff->description }}</p>
                        </div>
                        <div><a href="{{ route('tariff.buy', ['id' => $tariff->id]) }}" class="btn btn-color @if($tariff->is_hot) btn-accent @else btn-grey big @endif" type="button"><strong>{{ number_format($tariff->price, 0, '.', ' ') }}</strong><i class="fa fa-rouble"></i></a></div>
                    </div>
                @endforeach

                <div class="box-tarif--item">
                    <div>
                        <p class="txt-30bold">Тест-Драйв</p>
                        <p>Выбранный период</p>
                    </div>
                    <div>
                        <p>Подключайте тариф и пользуетесь им бесплатно в течении 3 дней.  Доступно 3 объявления.</p>
                    </div>
                    <div><a href="{{ route('register') }}" class="btn btn-color btn-accent b-shadow" type="button"><strong>Бесплатно</strong></a></div>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="authors" class="section-heading">
                <h2>Авторы</h2>
            </div>
            <div class="row section-mb">
                <div class="col-12 col-lg-5">
                    <figure class="figure h-100"><img class="figure-img" src="{{ asset('v2/img/author1.jpg') }}"></figure>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="box-simple h-100">
                        <h3 class="txt-28semibold">Немного о наших успехах</h3>
                        <p>Идея разработки сервиса принадлежит ООО “БИМПРО”, действующему&nbsp;лидеру и интегратору в области ТИМ (технологий информационного моделирования) и&nbsp;проектирования.</p>
                        <p>ООО “БИМПРО” является консультантом МИНСТРОЙ РФ и НОТИМ по&nbsp;<br>вопросам аналитики в&nbsp;области информационного моделирования</p>
                        <p>“Мы знаем, как это тяжело, найти специалиста (исполнителя или&nbsp;<br>подрядчика), будь то архитектор, дизайнер или инженер. Огромные затраты&nbsp;в адрес кадровых агентств омрачали нашу жизнь и не давали нужного&nbsp;результата.</p>
                        <p>Поиск специалистов по открытым каналам, в телеграмм, ВК и других&nbsp;<br>отнимал много времени, отсутствие систематизации мешало работать с&nbsp;найденными контактами.</p>
                        <p>После долгих мучений мы решили создать собственный проект, который&nbsp;заменил бы нам тендерный отдел или кадровое агентство, и так родился&nbsp;RADARIUM, наш незаменимый помощник.</p>
                        <p>&nbsp;</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="faq2" class="section-heading">
                <h2>Ответы на Вопросы</h2>
            </div>
            <div class="row section-mb">
                <div class="col-12 col-lg-8">
                    <div class="accordion" role="tablist" id="accordion-1">
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-1" aria-expanded="false" aria-controls="accordion-1 .item-1">Какие данные о специалистах вы собираете?</button></h2>
                            <div class="accordion-collapse collapse item-1" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-2" aria-expanded="true" aria-controls="accordion-1 .item-2">Что это за сервис и как он работает?</button></h2>
                            <div class="accordion-collapse collapse show item-2" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Наш сервис помогает находить специалистов в области проектирования, собирая и систематизируя данные из 50+ профильных источников. Вы получаете актуальную базу исполнителей и можете связаться с ними в один клик.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-3" aria-expanded="false" aria-controls="accordion-1 .item-3">Как выбрать подходящий тариф</button></h2>
                            <div class="accordion-collapse collapse item-3" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-4" aria-expanded="false" aria-controls="accordion-1 .item-4">Насколько точна ваша выборка?</button></h2>
                            <div class="accordion-collapse collapse item-4" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-5" aria-expanded="false" aria-controls="accordion-1 .item-5">Можно ли настроить фильтрацию специалистов по&nbsp;своим критериям?</button></h2>
                            <div class="accordion-collapse collapse item-5" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-6" aria-expanded="false" aria-controls="accordion-1 .item-6">Как быстро я&nbsp;смогу связаться с&nbsp;найденными специалистами?</button></h2>
                            <div class="accordion-collapse collapse item-6" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-7" aria-expanded="false" aria-controls="accordion-1 .item-7">Чем ваш сервис лучше ручного поиска?</button></h2>
                            <div class="accordion-collapse collapse item-7" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-8" aria-expanded="false" aria-controls="accordion-1 .item-8">Как я могу получить доступ к архивным данным?</button></h2>
                            <div class="accordion-collapse collapse item-8" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Nullam id dolor id nibh ultricies vehicula ut id elit. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Donec id elit non mi porta gravida at eget metus.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="box-simple box-faq">
                        <h2 class="text-center title-50semibold">Остались<br>Вопросы?</h2>
                        <p>Если у вас остались вопросы — не&nbsp;стесняйтесь обращаться. Команда поддержки быстро даст ответ и&nbsp;поможет с любым этапом.</p>
                        <button class="btn btn-color btn-accent" type="button">Нужна консультация</button>
                        <img class="faqman" src="{{ asset('v2/img/faqman.png') }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row section-finish">
                <div class="col">
                    <div class="finish-item">
                        <div class="finish-text">
                            <h3 class="title-40semibold">Предложите<br>Свою Идею</h3>
                            <p>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-telegram.svg') }}"></a>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-whatsapp.svg') }}"></a>
                            </p>
                        </div>
                        <div class="finish-img"><img src="{{ asset('v2/img/plastic-metall.png') }}"></div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="finish-item">
                        <div class="finish-text">
                            <h1 class="title-40semibold">Техническая<br>Поддержка</h1>
                            <p>
                                <a href="#"><img class="social" src="{{ asset('v2/img/icon-telegram.svg') }}"></a
                                ><a href="#"><img class="social" src="{{ asset('v2/img/icon-whatsapp.svg') }}"></a>
                            </p>
                        </div>
                        <div class="finish-img"><img src="{{ asset('v2/img/plastic-base.png') }}"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-landing-layout>
