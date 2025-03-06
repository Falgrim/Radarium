<tr id="author-id-{{ $author->id }}">
    <td>
        <img src="{{asset('images/avatar.jpg')}}" class="avatar_row">
    </td>
    <td class="align-middle text-center">
        {{ $author->getAvrSpecialistRating() }}
    </td>
    <td class="align-middle">
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
    </td>
    <td>
        @if(count($author->specialtiesWithShortName()))
        <span class="badge text-bg-secondary">{!! implode('</span><span class="badge text-bg-secondary">', $author->specialtiesWithShortName()) !!}</span>
        @endif
    </td>
    <td>
        {{ implode('; ', $author->specialistData()['soft_experience']) }}
    </td>
    <td class="text-">
        @if($author->lastPost()?->post)
            <em>{{ Str::limit($author->lastPost()->post, 100) }}</em>
        @endif
    </td>
    <td>

    </td>
    <td class="align-middle">
        <a href="{{ route('catalog.specialist.view', ['id' => $author->id]) }}" class="btn btn-light btn-sm ">Подробнее</a>
    </td>
</tr>
