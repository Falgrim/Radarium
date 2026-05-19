@props(['author', 'checkOpenContact' => false])

@if($checkOpenContact)
    @if($author->hasDirectTelegramContact())
        <p class="nickname">
            @if($author->username)
                <a href="https://t.me/{{ $author->username }}" target="_blank" rel="noopener noreferrer">{{ $author->username }}</a>
            @endif
        </p>

        @if($author->first_name)
            <p class="name">{{ trim($author->last_name.' '.$author->first_name) }}</p>
        @endif

        @if($author->phone)
            <p class="email">{{ trim($author->phone) }}</p>
        @endif
    @elseif($author->user_id)
        <p class="nickname">
            <a href="tg://user?id={{ $author->user_id }}" target="_blank" rel="noopener noreferrer">{{ $author->user_id }}</a>
        </p>
        @if($author->first_name)
            <p class="name">{{ trim($author->last_name.' '.$author->first_name) }}</p>
        @endif
        <p class="text-muted small mb-0">{{ $author->catalogIndirectContactHint() }}</p>
    @else
        @if($author->first_name)
            <p class="name">{{ trim($author->last_name.' '.$author->first_name) }}</p>
        @endif
        <p class="text-muted small mb-0">{{ $author->catalogIndirectContactHint() }}</p>
        @if($postUrl = $author->lastBuilderPostTelegramUrl())
            <p class="mb-0"><a href="{{ $postUrl }}" target="_blank" rel="noopener noreferrer">Перейти к сообщению в Telegram</a></p>
        @endif
    @endif
@else
    <i>Скрыто</i>
@endif
