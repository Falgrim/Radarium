@php
    $lastPost = $author->lastPost();
    $specialties = $author->specialtiesWithShortName(15);
    $checkOpenContact = Auth::user()->checkOpenContact($author);
@endphp

<tr id="author-id-{{ $author->id }}">
    <td>
        <img src="{{asset('images/avatar.jpg')}}" class="avatar_row">
    </td>
    <td class="align-middle text-center">
        {{ is_null($author->builder_reviews_avg_rating) ? 0 : number_format($author->builder_reviews_avg_rating, 1, '.', ' ') }}
    </td>
    <td class="align-middle">
        @if($checkOpenContact)
            @if($author->username)
                <a href="https://t.me/{{ $author->username }}" target="_blank">{{ $author->username }}</a>
            @elseif($author->user_id)
                <a href="tg://user?id=5127911621{{ $author->user_id }}" target="_blank">{{ $author->user_id }}</a>
            @else
                <i>Не известно</i>
            @endif

            @if($author->first_name)
                <br />{{ trim($author->last_name.' '.$author->first_name) }}
            @endif

            @if($author->phone)
                <br />{{ trim($author->phone) }}
            @endif
        @else
            <i>Скрыто</i>
        @endif
    </td>
    <td style="white-space: nowrap;">
        @if(count($specialties))
            {!! implode('<br />', $specialties) !!}
        @endif
    </td>
    <td>
        {{ implode('; ', $author->builderData()['soft_experience']) }}
    </td>
    <td class="text-">
        @if($lastPost?->post)
            <em>{{ $lastPost->post_date->format('d.m.Y') }}<br />{{ \App\Models\ApiPostUser::prepareLastPostText($lastPost->post, $checkOpenContact)  }}</em>
        @endif
    </td>
    <td>

    </td>
    <td class="align-middle">
        @if(Auth::user()->checkAccessToContact($author))
            @if($checkOpenContact)
                <a href="{{ route('catalog.builder.view', ['id' => $author->id]) }}" class="btn btn-primary btn-sm ">Контакт открыт</a>
            @else
                <a href="{{ route('catalog.builder.view', ['id' => $author->id]) }}" class="btn btn-light btn-sm ">Открыть контакт</a>
            @endif
        @endif
    </td>
</tr>
