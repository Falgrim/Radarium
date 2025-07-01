<x-landing-layout>
    <main>
        <div class="container">
            <div class="herobox">
                <div>
                    <h1>Сервис для поиска специалистов и подрядчиков с помощью ИИ</h1>
                    <p class="rowline">
                        <a class="btn rowline-item btn-trans btn-light" role="button" href="#industries"><br># Проектирование<br><br></a>
                        <a class="btn rowline-item btn-trans btn-light" role="button" href="#industries"><br># Строительство МСК<br><br></a>
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
                        <button class="btn btn-color btn-grey plastic" type="button" style="cursor: default;">Оставляйте комментарии и&nbsp;отзывы<img src="{{ asset('v2/img/icon-plastic.png') }}"></button>
                    </div>
                    <div class="drive-col--item dc-divider"><span></span></div>
                    <div class="drive-col--item">
                        <h3 class="title">Бесплатный доступ</h3>
                        <p>Вы получаете полный доступ ко всем ключевым функциям на ограниченное время</p>
                        @if(Auth::check())
                            <button class="btn btn-color btn-accent" type="button" data-bs-toggle="modal" data-bs-target="#rd-testdrive-auth">Тест–Драйв</button>
                        @else
                            <button class="btn btn-color btn-accent" type="button" data-bs-toggle="modal" data-bs-target="#rd-testdrive-guest">Тест–Драйв</button>
                        @endif
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
                    <p>Строительство</p><span class="note">в разработке</span>
                </div>
                <div class="tricols-item depo-plus">
                    <a class="btn" role="button" id="open-tg-chat" data-bs-target="#rd-industry" href="https://t.me/radarium_tech" target="_blank" rel="noopener noreferrer"><span class="plus-circle">+</span></a>
                    <p>Нужна другая отрасль</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="faq" class="section-heading">
                <h2 id="howto">Как работает Radarium?</h2>
            </div>
            <div class="row section-mb">
                <div class="col">
                    <div class="tabs-box">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item box-simple" role="presentation">
                                <a class="nav-link active" role="tab" data-bs-toggle="tab" href="#tab-1">
                                    <h4>Выберите&nbsp; специализацию и настройте фильтры</h4>
                                    <p>Можно выбрать одну или несколько. Поиск покажет всех, у&nbsp;кого встречается хотя бы&nbsp;одна.</p>
                                </a>
                            </li>
                            <li class="nav-item box-simple" role="presentation">
                                <a class="nav-link" role="tab" data-bs-toggle="tab" href="#tab-2">
                                    <h4>Изучите подборку, найдите интересующих Вас кандидатов</h4>
                                    <p>В подборке доступны основные данные, ключевые теги, а также фрагмент последнего сообщения.</p>
                                </a>
                            </li>
                            <li class="nav-item box-simple" role="presentation">
                                <a class="nav-link" role="tab" data-bs-toggle="tab" href="#tab-3">
                                    <h4>Откройте карточку специалиста</h4>
                                    <p>В карточке специалиста представлены данные о специалисте и история объявлений. Свяжитесь со специалистами доступными способами.</p>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active pane-01" role="tabpanel" id="tab-1"><img class="img-fluid" width="1501" height="1112" src="{{ asset('v2/img/image1.png') }}"></div>
                            <div class="tab-pane pane-02" role="tabpanel" id="tab-2"><img class="img-fluid" width="1501" height="1112" src="{{ asset('v2/img/image2.png') }}"></div>
                            <div class="tab-pane pane-03" role="tabpanel" id="tab-3"><img class="img-fluid" width="1501" height="1112" src="{{ asset('v2/img/image3.png') }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="section-heading">
                <h2 id="why">Почему Radarium уникален?</h2>
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
                <h2 id="tarifs">Тарифы</h2>
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
                        <div>
                            @if(Auth::check())
                                <a href="{{ route('tariff.buy', ['id' => $tariff->id]) }}" class="btn btn-color @if($tariff->is_hot) btn-accent @else btn-grey big @endif" type="button"><strong>{{ number_format($tariff->price, 0, '.', ' ') }}</strong><i class="fa fa-rouble"></i></a>
                            @else
                                <button data-bs-toggle="modal" data-bs-target="#rd-tariff-guest" type="button" class="btn btn-color @if($tariff->is_hot) btn-accent @else btn-grey big @endif"><strong>{{ number_format($tariff->price, 0, '.', ' ') }}</strong><i class="fa fa-rouble"></i></button>
                            @endif
                        </div>
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
                    <div>
                        @if(Auth::check())
                            <button class="btn btn-color btn-accent b-shadow" data-bs-toggle="modal" data-bs-target="#rd-testdrive-auth" type="button"><strong>Бесплатно</strong></button>
                        @else
                            <button class="btn btn-color btn-accent b-shadow" data-bs-toggle="modal" data-bs-target="#rd-testdrive-guest" type="button"><strong>Бесплатно</strong></button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="container" hidden="">
            <div id="authors" class="section-heading">
                <h2 id="author">Авторы</h2>
            </div>
            <div class="row section-mb">
                <div class="col-12 col-lg-5">
                    <figure class="figure h-100"><img class="figure-img" src="{{ asset('v2/img/author1.jpg') }}"></figure>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="h-100 box-simple">
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
                                    <p class="mb-0">Мы собираем только те данные, которые специалист сам предоставил в открытых Telegram-каналах и посчитал достаточными для определения его квалификации.</p>
                                    <p class="mt-3">В большинстве случаев исполнитель предоставляет информацию о своих специализациях, профильных навыках, программном обеспечении, которым владеет, в сообщении раскрывает детальную информацию, которая поможет вам определиться с выбором специалиста, а также контактные данные для связи с ним.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-3" aria-expanded="false" aria-controls="accordion-1 .item-3">Как выбрать подходящий тариф</button></h2>
                            <div class="accordion-collapse collapse item-3" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">В первую очередь решение, какой тариф вам выбрать, зависит от потребностей, которые вы желаете закрыть.</p>
                                    <p class="mt-3">Для ознакомления с сервисом каждому новому пользователю доступен тестовый период, в рамках которого можно изучить систему и сразу найти нужного специалиста.</p>
                                    <p class="mt-3">Определите примерное количество контактов, которое вам нужно, и выберите тариф, максимально соответствующий вашим потребностям по цене и объему. Система тарифов выстроена по принципу «скользящей шкалы»: чем больше объем услуг, тем дешевле стоимость. Если вы регулярно осуществляете поиск специалистов, то для оптимизации расходов при подборе исполнителей, рекомендуем выбирать самый большой тариф для экономии. </p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-4" aria-expanded="false" aria-controls="accordion-1 .item-4">Насколько точна ваша выборка?</button></h2>
                            <div class="accordion-collapse collapse item-4" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Мы — эксперты в области BIM-проектирования. При создании Radarium мы опирались на наш богатый опыт в подборе персонала и внедрили передовые технологии искусственного интеллекта. В результате получился продукт, который позволяет найти профильного специалиста всего за несколько кликов. Точность поиска обеспечивается использованием современных решений и тщательным ручным контролем. Кроме того, мы осуществляем модерацию данных для исключения нерелевантной информации, гарантируя высокое качество результатов.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-6" aria-expanded="false" aria-controls="accordion-1 .item-6">Как осуществляется поиск специалистов?</button></h2>
                            <div class="accordion-collapse collapse item-6" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Наш сервис ищет исполнителей по вашему запросу среди большого количества профильных каналов в Телеграмм, где специалисты оставляют свои резюме, делятся опытом, ищут заказчиков и так далее. Сервис обрабатывает информацию и собирает нужных людей в одном месте – и вы сразу можете написать каждому из них.</p>
                                    <p class="mt-3">Чтобы найти подходящего именно вам исполнителя просто укажите нужную отрасль, специализацию или навыки. Также вы можете указать вашу конкретную задачу и найти исполнителей, которые имеют опыт в этом.</p>
                                    <p class="mt-3">После этого перед вами появится список подходящих по ваш запрос специалистов с краткой информацией об их опыте, квалификации и навыках</p>
                                    <p class="mt-3">Хотите узнать больше информации о специалисте или связаться с ним? Просто зайдите в карточку исполнителя</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-8" aria-expanded="false" aria-controls="accordion-1 .item-8">Как я могу получить доступ к архивным данным?</button></h2>
                            <div class="accordion-collapse collapse item-8" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Радариум позволяет мгновенно связываться с исполнителем, поскольку каждый из них оставляет свои контакты. Это может быть ссылка на его аккаунт, мобильный телефон или электронная почта.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" role="tab"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-1 .item-9" aria-expanded="false" aria-controls="accordion-1 .item-9">Чем ваш сервис лучше ручного поиска?</button></h2>
                            <div class="accordion-collapse collapse item-9" role="tabpanel" data-bs-parent="#accordion-1">
                                <div class="accordion-body">
                                    <p class="mb-0">Наш сервис на основе ИИ превосходит ручной поиск по нескольким ключевым причинам:</p>
                                    <p class="mt-3">Быстрота и эффективность: Radarium способен обрабатывать большие объемы данных за короткое время, находя нужную информацию мгновенно, тогда как ручной поиск занимает значительно больше времени.</p>
                                    <p class="mt-3">Точность и релевантность: Radarium использует алгоритмы машинного обучения и анализа данных для определения наиболее подходящих результатов, что повышает качество поиска и уменьшает количество нерелевантных результатов.</p>
                                    <p class="mt-3">Автоматизация процессов: Radarium может автоматически фильтровать, сортировать и анализировать данные, освобождая пользователя от рутинных задач и позволяя сосредоточиться на более важных аспектах.</p>
                                    <p class="mt-3">В целом, использование Radarium в поиске обеспечивает более быстрый, точный и удобный процесс нахождения нужной информации по сравнению с традиционными ручными методами.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="box-simple box-faq">
                        <h2 class="text-center title-50semibold">Остались<br>Вопросы?</h2>
                        <p>Если у вас остались вопросы — не&nbsp;стесняйтесь обращаться. Команда поддержки быстро даст ответ и&nbsp;поможет с любым этапом.</p>
                        <a class="btn btn-color btn-accent" role="button" id="open-tg-consult" href="https://t.me/radarium_tech" target="_blank" rel="noopener noreferrer">Нужна консультация</a>
                        <img class="faqman" src="{{ asset('v2/img/faqman.png') }}">
                    </div>
                </div>
            </div>
        </div>

        <x-footer-finish />

    </main>
</x-landing-layout>
