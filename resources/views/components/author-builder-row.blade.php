@php
    $lastPost = $author->lastBuilderPost();
    $checkOpenContact = isset($userOpenLog[$author->id]);
    $builderData = $author->builderData();
    $builderSpecialties = $author->builderSpecialtiesWithShortName(15);
@endphp

<tr id="author-id-{{ $author->id }}">
    <td class="def-cell-01">
        <figure class="figure pers">
            <img class="img-fluid figure-img" src="{{ $author->getPhoto() }}">
            <figcaption class="figure-caption" hidden="">{{ is_null($author->builder_reviews_avg_rating) ? 0 : number_format($author->builder_reviews_avg_rating, 1, '.', ' ') }}</figcaption>
        </figure>
    </td>
    <td class="def-cell-02">
        @if($tariffAccess OR $checkOpenContact)
            @if($checkOpenContact)
        <div class="show-room open" onclick="window.open('{{ route('catalog.builder.view', ['id' => $author->id]) }}', '_blank');">
            @else
        <div class="show-room" onclick="window.open('{{ route('catalog.builder.view', ['id' => $author->id]) }}', '_blank'); setTimeout(() => location.reload(), 1000);">
            @endif
        @else
            <div class="show-room" data-bs-toggle="modal" data-bs-target="#loginAlert">
        @endif
            <div class="contact-box">
                <p>Открыть контакт</p>
            </div>
            <div class="contact-info">
                <x-builder-catalog-contact :$author :check-open-contact="$checkOpenContact" />
            </div>
        </div>
    </td>
    <td class="def-cell-03">
        @if(count($builderSpecialties))
            @foreach($builderSpecialties as $spec)
                <p class="ellipse" title="{{ $spec['name'] }}">{!! $spec['name'] !!}</p>
            @endforeach
        @endif
    </td>
    <td class="def-cell-04">
        <p>
            <span class="pro-tag">{!! implode('</span> <span class="pro-tag">', \App\Models\ApiPostUser::profileSkillsFront($builderData['soft_experience'], $builderSpecialties)) !!}</span>
        </p>
    </td>
    <td class="def-cell-05">
        @if($lastPost?->post)
            <p class="author-last-post-full"><span class="txt-date">{{ $lastPost->post_date->format('d.m.Y') }}</span>{!! \App\Models\ApiPostUser::prepareLastPostText($lastPost->post, $checkOpenContact)->toHtml() !!}</p>
        @endif
    </td>
    <td class="def-cell-06">
    </td>
</tr>
<tr>
    <td class="row-divider" colspan="6">
        <div></div>
    </td>
</tr>
