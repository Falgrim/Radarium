@php
    $lastPost = $author->lastPost();
    $specialties = $author->specialtiesWithShortName(15);
    $checkOpenContact = isset($userOpenLog[$author->id]);
    $specialistData = $author->specialistData();
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
        @else
            <i>Скрыто</i>
        @endif
    </td>
    <td style="white-space: nowrap;">
        @if(count($specialties))
            @foreach($specialties as $specialist)
                {!! $specialist['name'] !!}<br />
            @endforeach
        @endif
    </td>
    <td>
        <span class="badge text-bg-secondary">{!! implode('</span> <span class="badge text-bg-secondary">', \App\Models\ApiPostUser::profileSkillsFront($specialistData['soft_experience'], $specialties)) !!}</span>
    </td>
    <td class="text-">
        @if($lastPost?->post)
            <em>{{ $lastPost->post_date->format('d.m.Y') }}<br />{{ \App\Models\ApiPostUser::prepareLastPostText($lastPost->post, $checkOpenContact)  }}</em>
        @endif
    </td>
    <td>

    </td>
    <td class="align-middle">
        @if($tariffAccess OR $checkOpenContact)
            @if($checkOpenContact)
                <a href="{{ route('catalog.specialist.view', ['id' => $author->id]) }}" class="btn btn-primary btn-sm ">Контакт открыт</a>
            @else
                <a href="{{ route('catalog.specialist.view', ['id' => $author->id]) }}" class="btn btn-light btn-sm ">Открыть контакт</a>
            @endif
        @else
            <a href="#" data-bs-toggle="modal" data-bs-target="#loginAlert" class="btn btn-light btn-sm ">Открыть контакт</a>
        @endif
    </td>
</tr>
