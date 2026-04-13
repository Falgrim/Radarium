@php
    $specialtiesWithShortName = $author->builderSpecialtiesWithShortName();
    $builderData = $author->builderData();
@endphp

<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Строительство</span></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('catalog.builders') }}"><span>Поиск</span></a></li>
                        <li class="breadcrumb-item active"><span>Строитель</span></li>
                    </ol>
                </div>
            </div>
        </div>
        <section class="pers2cols">
            <div class="container">

                @session('status')
                <div class="row">
                    <div class="alert alert-info">
                        <ul>
                            <li>{{ $value }}</li>
                        </ul>
                    </div>
                </div>
                @endsession

                @session('success')
                <div class="row">
                    <div class="alert alert-info">
                        <ul>
                            <li>{{ session()->get('success') }}</li>
                        </ul>
                    </div>
                </div>
                @endsession

                @if ($errors->specialist->any())
                <div class="row">
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->specialist->all() as $key => $error)
                                <li>{{ $key }} - {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                @if ($errors->review->any())
                <div class="row">
                    <div class="alert alert-danger">
                        <h4>Ошибка добавления отзыва</h4>
                        <ul>
                            @foreach ($errors->review->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                <div class="row">
                    <div class="col-lg-4 col-xxl-3">
                        <div class="pers-box">
                            <figure class="figure pers">
                                <img class="img-fluid figure-img" src="{{ $author->getPhoto() }}">
                                <figcaption class="figure-caption" hidden="">{{ is_null($author->builder_reviews_avg_rating) ? 0 : number_format($author->builder_reviews_avg_rating, 1, '.', ' ') }}</figcaption>
                                <button class="btn btn-trans btn-link likes" type="button" hidden=""><img src="{{ asset('v2/img/icon-heart-btn.svg') }}"></button>
                            </figure>
                            <div class="pers-info">
                                <p>
                                    <span><strong>
                                        @if($author->username)
                                            {{ '@'.$author->username }}
                                        @elseif($author->user_id)
                                            {{ $author->user_id }}
                                        @else
                                            <i>Не известно</i>
                                        @endif
                                    </strong></span>
                                    @if($author->first_name)
                                        <span>{{ trim($author->last_name.' '.$author->first_name) }}</span>
                                    @endif
                                </p>
                                <p>
                                    @if($author->phone)
                                        <span><strong><strong>{{ trim($author->phone) }}</strong></strong></span>
                                    @endif
                                </p>
                            </div>

                            <div class="pers-comment" hidden="">
                                <h5>Мои комментарии</h5><button class="btn w-100 btn-trans" type="button">Оставить комментарий</button>
                                <p><span>02.12.2025</span>Делает только многоэтажку, не работаем</p>
                                <p><span>02.12.2025</span>Перезвонить через месяц</p>
                                <p><span>02.12.2025</span>Позвонить когда начнем проект по перестройке помещения( у него есть бригада)</p>
                            </div>

                            @if($author->username)
                                <button class="btn btn-color btn-accent" type="button" onclick="window.location.href='https://t.me/{{ $author->username }}'">Написать в Telegram</button>
                            @endif

                            <button class="btn w-100 btn-trans moderation-alert" type="button" data-bs-toggle="modal" data-bs-target="#moderationAlert" data-type="ApiPostUser" data-id="{{ $author->id }}">Сообщить об ошибке</button>
                        </div>
                    </div>
                    <div class="col">
                        <div class="pers-box">
                            <article class="cards">
                                <div class="cards-item">
                                    <h4>Специализации</h4>
                                    @if(count($specialtiesWithShortName))
                                        @foreach($specialtiesWithShortName as $shotName)
                                            <p>{!! $shotName['name'] !!}</p>
                                        @endforeach
                                    @endif
                                </div>
                                <div class="cards-item">
                                    <h4>Профессиональные навыки</h4>
                                    <p class="pro-tags">
                                        <span class="pro-tag">{!! implode('</span><span class="pro-tag">', \App\Models\ApiPostUser::profileSkillsFront($builderData['soft_experience'] ?? [], $specialtiesWithShortName)) !!}</span>
                                    </p>
                                </div>
                            </article>
                            <article class="rd-card rd-card-blue">
                                <h4 class="rd-card-title">Последнее сообщение</h4>
                                <div class="rd-card-body">
                                    @if($author->lastBuilderPost()?->post)
                                    <p class="par2cols">
                                        <span>{{ $author->lastBuilderPost()?->post }}</span>
                                        <span>{{ $author->lastBuilderPost()?->post_date->format("d.m.Y") }}</span>
                                    </p>
                                    @endif
                                </div>
                            </article>
                            <article hidden="">
                                <div class="show-content">
                                    <a class="btn show-me" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-1" role="button">Полезные данные<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-up">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"></path>
                                        </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-down">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path>
                                        </svg></a>
                                    <div class="collapse" id="collapse-1">
                                        <div class="rd-card-body">
                                            <p>Сертифицированный специалист в области BIM (Building Information Modeling) с опытом внедрения и сопровождения BIM-процессов на всех стадиях жизненного цикла объекта. Владение Revit, Navisworks, Dynamo, AutoCAD, Civil 3D, Archicad. Участвовал в крупных инфраструктурных и гражданских проектах. Есть опыт координации многодисциплинарных моделей, работы с clash detection, а также подготовки документации в соответствии с стандартами (ГОСТ, ISO 19650).</p>
                                        </div>
                                    </div>
                                </div>
                            </article>
                            <article>
                                <div class="show-content">
                                    <a class="btn show-me" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-2" href="#collapse-2" role="button">История сообщений<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-up">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"></path>
                                        </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-down">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path>
                                        </svg>
                                    </a>
                                    <div class="collapse" id="collapse-2">
                                        <div class="rd-card-body">
                                            @if($author->postsComplete->count())
                                                @foreach ($author->postsComplete as $post)
                                                    <p class="par2cols">
                                                        <span>{{ $post->post }}</span>
                                                        <span><span>{{ $post->post_date->format("d.m.Y") }}</span></span>
                                                    </p>
                                                    @if(!$loop->last)
                                                    <hr>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                            <article>
                                <div class="show-content">
                                    <a class="btn show-me" data-bs-toggle="collapse" aria-expanded="true" aria-controls="collapse-3" href="#collapse-3" role="button">Обсуждения и Отзывы<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-up">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"></path>
                                        </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor" class="chevron-down">
                                            <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                            <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path>
                                        </svg></a>
                                    <div class="collapse show show" id="collapse-3">
                                        <div class="rd-card-body">
                                            <button class="btn mb-4 btn-trans" type="button"  data-bs-toggle="modal" data-bs-target="#reviewModal">Оставить публичный отзыв</button>

                                            @if($reviews->count())
                                                @foreach ($reviews as $review)
                                                    <x-review-row :rowId="$author->id" :$review :$request :$errors />

                                                    @if(!$loop->last)
                                                        <hr>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <x-footer-finish />
    </main>

    <x-review_form :rowId="$author->id" :$request :$errors />

</x-global-layout>