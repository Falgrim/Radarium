@php
    $isBuilders = request()->routeIs('catalog.builders');
    $isSpecialists = request()->routeIs('catalog.specialists');
    $clearUrl = $isBuilders ? route('catalog.builders') : route('catalog.specialists');
@endphp

<div class="row-flex catalog-search-tabs-row">
    <div class="catalog-search-tabs-bar">
        <a href="{{ route('catalog.builders') }}"
           class="catalog-search-tab {{ $isBuilders ? 'is-active is-active-tab-builders' : '' }}"
           @if ($isBuilders) aria-current="page" @endif>Строительство</a>
        <a href="{{ route('catalog.specialists') }}"
           class="catalog-search-tab {{ $isSpecialists ? 'is-active is-active-tab-specialists' : '' }}"
           @if ($isSpecialists) aria-current="page" @endif>Проектирование</a>
    </div>
    <button class="btn btn-link btn-trans btn-trash catalog-search-clear" type="button"
            onclick="window.location.href='{{ $clearUrl }}'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="-32 0 512 512" width="1em" height="1em" fill="currentColor">
            <path d="M135.2 17.7C140.6 6.8 151.7 0 163.8 0H284.2c12.1 0 23.2 6.8 28.6 17.7L320 32h96c17.7 0 32 14.3 32 32s-14.3 32-32 32H32C14.3 96 0 81.7 0 64S14.3 32 32 32h96l7.2-14.3zM32 128H416V448c0 35.3-28.7 64-64 64H96c-35.3 0-64-28.7-64-64V128zm96 64c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16V432c0 8.8 7.2 16 16 16s16-7.2 16-16V208c0-8.8-7.2-16-16-16z"></path>
        </svg>
        <span>Очистить поиск</span>
    </button>
</div>
