@php
    $specialtiesWithShortName = $author->specialtiesWithShortName();
@endphp

<x-global-layout>
    @session('status')
    <div class="alert alert-info">
        <ul>
            <li>{{ $value }}</li>
        </ul>
    </div>
    @endsession

    @if ($errors->specialist->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->specialist->all() as $key => $error)
                    <li>{{ $key }} - {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="container my-5 profile_page">
        <h1 class="text-body-emphasis text-center">Карточка специалиста</h1>

        <div class="row mt-3">
            <div class="col-4">
                <img src="{{asset('images/avatar.jpg')}}" class="avatar_row">
                <div class="row bg-block-blue">
                    @if($author->username)
                        <a href="https://t.me/{{ $author->username }}" target="_blank">{{ $author->username }}</a>
                    @elseif($author->user_id)
                        <a href="tg://user?id={{ $author->user_id }}" target="_blank">{{ $author->user_id }}</a>
                    @else
                        <i>Не известно</i>
                    @endif

                    @if($author->first_name)
                        <br />{{ trim($author->last_name.' '.$author->first_name) }}
                    @endif

                    @if($author->phone)
                        <br />{{ trim($author->phone) }}
                    @endif

                    @if(Auth::check())
                        <button class="btn-sm btn-danger moderation-alert" data-bs-toggle="modal" data-bs-target="#moderationAlert" data-type="ApiPostUser" data-id="{{ $author->id }}" type="button">Есть ошибка!</button>
                    @endif
                </div>
            </div>
            <div class="col-8">
                <div class="row">
                    <div class="col-6">
                        <h4>Специализации</h4>
                        <div class="bg-block-blue">
                        @if(count($specialtiesWithShortName))
                            @foreach($specialtiesWithShortName as $shotName)
                                    <span class="badge text-bg-secondary">{!! $shotName['name'] !!}</span>
                            @endforeach
                        @endif
                        </div>
                    </div>
                    <div class="col-6 pr-0.5">
                        <h4>Теги/профессиональные навыки</h4>
                        <div class="bg-block-blue">
                        {{ implode('; ', $author->builderData()['soft_experience']) }}
                        </div>
                    </div>
                </div>
                @if($author->lastPost()?->post)
                <div class="row mt-3">
                    <h4>Последнее сообщение</h4>
                    <div class="col-3 bg-block-blue">
                        <em>{{ $author->lastPost()?->post_date->format("d.m.Y") }}</em>
                    </div>
                    <div class="col-9 bg-block-blue">
                        <em>{{ $author->lastPost()?->post }}</em>
                    </div>
                </div>
                @endif
                @if($author->posts->count())
                    <div class="row mt-3">
                        <h4>История сообщений</h4>
                        @foreach ($author->posts as $post)
                        <div class="col-3 bg-block-blue">
                            <em>{{ $post->post_date->format("d.m.Y") }}</em>
                        </div>
                        <div class="col-9 bg-block-blue">
                            <em>{{ $post->post }}</em>
                        </div>
                        @endforeach
                    </div>
                @endif
                @if($reviews->count())
                <div class="row mt-3">
                    <h4>Обсуждения и отзывы</h4>
                    @foreach ($reviews as $review)
                        <x-review-row :rowId="$author->id" :$review :$request :$errors />
                    @endforeach
                </div>
                @endif
                <div class="row mt-3">
                    <x-review_form :rowId="$author->id" :$request :$errors />
                </div>
            </div>
        </div>
    </div>
</x-global-layout>
