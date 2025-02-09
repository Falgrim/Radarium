<tr id="specialist-id-{{ $specialist->id }}">
    <td>
        <img src="{{asset('images/avatar.jpg')}}" class="avatar_row">
    </td>
    <td class="align-middle text-center">
        {{ $specialist->getAvrRating() }}
    </td>
    <td class="align-middle">
        @if($specialist->user?->username)
            <a href="https://t.me/{{ $specialist->user->username }}" target="_blank">{{ $specialist->user->username }}</a>
        @elseif($specialist->user?->user_id)
            <a href="tg://user?id=5127911621{{ $specialist->user->user_id }}" target="_blank">{{ $specialist->user->user_id }}</a>
        @else
            <i>Не известно</i>
        @endif

        @if($specialist->user?->first_name)
            <br />{{ trim($specialist->user->last_name.' '.$specialist->user->first_name) }}
        @endif

        @if($specialist->user?->phone)
            <br />{{ trim($specialist->phone) }}
        @endif
    </td>
    <td>
        @if(count($specialist->specialtiesWithTitle()))
        <span class="badge text-bg-secondary">{!! implode('</span><br /><span class="badge text-bg-secondary">', $specialist->specialtiesWithTitle()) !!}</span>
        @endif
    </td>
    <td>

    </td>
    <td class="text-">
        @if($specialist->post?->post)
            <em>{{ Str::limit($specialist->post?->post, 100) }}</em>
        @endif
    </td>
    <td>

    </td>
    <td class="align-middle">
        <a href="{{ route('catalog.specialist.view', ['id' => $specialist->id]) }}" class="btn btn-light btn-sm ">Подробнее</a>
    </td>
</tr>
