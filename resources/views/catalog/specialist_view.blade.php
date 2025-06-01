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
                    @if($specialist->user?->username)
                        <a href="https://t.me/{{ $specialist->user->username }}" target="_blank">{{ $specialist->user->username }}</a>
                    @elseif($specialist->user?->user_id)
                        <a href="tg://user?id={{ $specialist->user->user_id }}" target="_blank">{{ $specialist->user->user_id }}</a>
                    @else
                        <i>Не известно</i>
                    @endif

                    @if($specialist->user?->first_name)
                        <br />{{ trim($specialist->user->last_name.' '.$specialist->user->first_name) }}
                    @endif

                    @if($specialist->user?->phone)
                        <br />{{ trim($specialist->phone) }}
                    @endif
                </div>
            </div>
            <div class="col-8">
                <div class="row">
                    <div class="col-6">
                        <h4>Специализации</h4>
                        <div class="bg-block-blue">
                        @if(count($specialist->specialtiesWithShortName()))
                            <span class="badge text-bg-secondary">{!! implode('</span><span class="badge text-bg-secondary">', $specialist->specialtiesWithShortName()) !!}</span>
                        @endif
                        </div>
                    </div>
                    <div class="col-6 pr-0.5">
                        <h4>Теги/профессиональные навыки</h4>
                        <div class="bg-block-blue">
                        {{ $specialist->soft_experience }}
                        </div>
                    </div>
                </div>
                @if($specialist->post?->post)
                <div class="row mt-3">
                    <h4>Последнее сообщение</h4>
                    <div class="col-3 bg-block-blue">
                        <em>{{ $specialist->post?->post_date->format("d.m.Y") }}</em>
                    </div>
                    <div class="col-9 bg-block-blue">
                        <em>{{ $specialist->post?->post }}</em>
                    </div>
                </div>
                @endif
                @if($specialist->user?->posts->count())
                    <div class="row mt-3">
                        <h4>История сообщений</h4>
                        @foreach ($specialist->user->posts as $post)
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
                        <x-review-row :rowId="$specialist->id" :$review :$request :$errors />
                    @endforeach
                </div>
                @endif
                <div class="row mt-3">
                    <x-review_form :rowId="$specialist->id" :$request :$errors />
                </div>
            </div>
        </div>
    </div>
</x-global-layout>
