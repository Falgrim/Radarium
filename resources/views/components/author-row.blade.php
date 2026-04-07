@php
    $lastPost = $author->lastPost();
    $specialties = $author->specialtiesWithShortName(15);
    $checkOpenContact = isset($userOpenLog[$author->id]);
    $specialistData = $author->specialistData();
@endphp

<tr id="author-id-{{ $author->id }}">
    <td class="def-cell-01">
        <figure class="figure pers">
            <img class="img-fluid figure-img" src="{{ $author->getPhoto() }}">
            <figcaption class="figure-caption" hidden="">{{ is_null($author->specialist_reviews_avg_rating) ? 0 : number_format($author->specialist_reviews_avg_rating, 1, '.', ' ') }}</figcaption>
        </figure>
    </td>
    <td class="def-cell-02">
        @if($tariffAccess OR $checkOpenContact)
            @if($checkOpenContact)
        <div class="show-room open" onclick="window.open('{{ route('catalog.specialist.view', ['id' => $author->id]) }}', '_blank');">
            @else
        <div class="show-room" onclick="window.open('{{ route('catalog.specialist.view', ['id' => $author->id]) }}', '_blank'); setTimeout(() => location.reload(), 1000);">
            @endif
        @else
            <div class="show-room" data-bs-toggle="modal" data-bs-target="#loginAlert">
        @endif
            <div class="contact-box">
                <p>Открыть контакт</p>
            </div>
            <div class="contact-info">
                @if($checkOpenContact)
                    <p class="nickname">
                    @if($author->username)
                        <a href="https://t.me/{{ $author->username }}" target="_blank">{{ $author->username }}</a>
                    @elseif($author->user_id)
                        <a href="tg://user?id={{ $author->user_id }}" target="_blank">{{ $author->user_id }}</a>
                    @else
                        <i>Не известно</i>
                    @endif
                    </p>

                    @if($author->first_name)
                        <p class="name">{{ trim($author->last_name.' '.$author->first_name) }}</p>
                    @endif

                    @if($author->phone)
                        <p class="email">{{ trim($author->phone) }}</p>
                    @endif
                @else
                    <i>Скрыто</i>
                @endif
            </div>
        </div>
    </td>
    <td class="def-cell-03">
        @if(count($specialties))
            @foreach($specialties as $specialist)
                <p class="ellipse" title="{{ $specialist['name'] }}">{!! $specialist['name'] !!}</p>
            @endforeach
        @endif
    </td>
    <td class="def-cell-04">
        <p>
            <span class="pro-tag">{!! implode('</span> <span class="pro-tag">', \App\Models\ApiPostUser::profileSkillsFront($specialistData['soft_experience'], $specialties)) !!}</span>
        </p>
    </td>
    <td class="def-cell-05">
        @if($lastPost?->post)
            <p class="author-last-post-full"><span class="txt-date">{{ $lastPost->post_date->format('d.m.Y') }}</span>{{ \App\Models\ApiPostUser::prepareLastPostText($lastPost->post, $checkOpenContact, true) }}</p>
        @endif
    </td>
    <td class="def-cell-06">
        {{--<p class="line-clamp"><span class="txt-date">13.04.2025</span>Делает только под ключ, долго выполняет работу и не качестве...</p>--}}
    </td>
</tr>
<tr>
    <td class="row-divider" colspan="6">
        <div></div>
    </td>
</tr>
