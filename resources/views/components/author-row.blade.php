@php
    $lastPost = $author->lastPost();
    $specialties = $author->specialtiesWithShortName();
    $checkOpenContact = Auth::user()->checkOpenContact($author);
@endphp

<tr id="author-id-{{ $author->id }}">
    <td>
        <img src="{{ $author->getPhoto() }}" class="avatar_row">
    </td>
    <td class="align-middle text-center">
        {{ is_null($author->specialist_reviews_avg_rating) ? 0 : number_format($author->specialist_reviews_avg_rating, 1, '.', ' ') }}
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
    <td>
        @if(count($specialties))
        <span class="badge text-bg-secondary">{!! implode('</span><span class="badge text-bg-secondary">', $specialties) !!}</span>
        @endif
    </td>
    <td>
        {{ implode('; ', $author->specialistData()['soft_experience']) }}
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
        <a href="{{ route('catalog.specialist.view', ['id' => $author->id]) }}" class="btn btn-light btn-sm ">Подробнее</a>
        @endif
    </td>
</tr>
