@php
    $specialtiesWithShortName = $author->specialtiesWithShortName();
    $specialistData = $author->specialistData();
@endphp

<x-global-layout>
    <main>
        <div class="container">
            <div class="row">
                <div class="col">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}"><span>Проектирование</span></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('catalog.specialists') }}"><span>Поиск</span></a></li>
                        <li class="breadcrumb-item active"><span>Специалист</span></li>
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
                                <figcaption class="figure-caption" hidden="">{{ is_null($author->specialist_reviews_avg_rating) ? 0 : number_format($author->specialist_reviews_avg_rating, 1, '.', ' ') }}</figcaption>
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

                            {{--<div class="rate-box">
                                <h5>Моя оценка
                                    <span><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor">
                                                <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                                <path d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z"></path>
                                            </svg></span><span>3.5</span></span>
                                </h5>

                                <p class="rate-stars">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="star yes">
                                        <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                        <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
                                    </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="star yes">
                                        <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                        <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
                                    </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="star yes">
                                        <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                        <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
                                    </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="star">
                                        <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                        <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
                                    </svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -32 576 576" width="1em" height="1em" fill="currentColor" class="star">
                                        <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2023 Fonticons, Inc. -->
                                        <path d="M287.9 0c9.2 0 17.6 5.2 21.6 13.5l68.6 141.3 153.2 22.6c9 1.3 16.5 7.6 19.3 16.3s.5 18.1-5.9 24.5L433.6 328.4l26.2 155.6c1.5 9-2.2 18.1-9.6 23.5s-17.3 6-25.3 1.7l-137-73.2L151 509.1c-8.1 4.3-17.9 3.7-25.3-1.7s-11.2-14.5-9.7-23.5l26.2-155.6L31.1 218.2c-6.5-6.4-8.7-15.9-5.9-24.5s10.3-14.9 19.3-16.3l153.2-22.6L266.3 13.5C270.4 5.2 278.7 0 287.9 0zm0 79L235.4 187.2c-3.5 7.1-10.2 12.1-18.1 13.3L99 217.9 184.9 303c5.5 5.5 8.1 13.3 6.8 21L171.4 443.7l105.2-56.2c7.1-3.8 15.6-3.8 22.6 0l105.2 56.2L384.2 324.1c-1.3-7.7 1.2-15.5 6.8-21l85.9-85.1L358.6 200.5c-7.8-1.2-14.6-6.1-18.1-13.3L287.9 79z"></path>
                                    </svg>
                                </p>
                            </div>--}}
                            <button class="btn w-100 btn-trans moderation-alert" type="button" data-bs-toggle="modal" data-bs-target="#moderationAlert" data-type="ApiPostUser" data-id="{{ $author->id }}" data-api-channel-post-id="{{ $author->lastPost()?->id }}">Сообщить об ошибке</button>
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
                                        <span class="pro-tag">{!! implode('</span><span class="pro-tag">', \App\Models\ApiPostUser::profileSkillsFront($specialistData['soft_experience'], [])) !!}</span>
                                    </p>
                                </div>
                            </article>
                            <article class="rd-card rd-card-blue">
                                <h4 class="rd-card-title">Последнее сообщение</h4>
                                <div class="rd-card-body">
                                    @if($author->lastPost()?->post)
                                    <p class="par2cols">
                                        <span>{{ $author->lastPost()?->post }}</span>
                                        <span>{{ $author->lastPost()?->post_date->format("d.m.Y") }}</span>
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

