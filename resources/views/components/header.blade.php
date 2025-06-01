@php
    $contactsLimit = Auth::check() ? Auth::user()->getLeftContacts() : [];
@endphp

<header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom text-bg-dark fixed-top">
    <div class="col-md-3 mb-2 mb-md-0 d-none d-md-block">
        <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none">
            Radarium
        </a>
    </div>

    <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
        <li><a href="{{ route('index') }}" class="nav-link px-2">Главная</a></li>

        <li>
            <div class="dropdown">
                <a class="nav-link px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Специалисты @if(Auth::check() AND $contactsLimit['count_contacts']) <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">{{ $contactsLimit['count_contacts_left'] }} из {{ $contactsLimit['count_contacts'] }}<span> @endif
                </a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('catalog.specialists') }}" class="dropdown-item px-2">Проектирование</a></li>
                    <li><a href="{{ route('catalog.builders') }}" class="dropdown-item px-2">Строительство</a></li>
                </ul>
            </div>
        </li>

        <li><a href="{{ route('catalog.companyjobs') }}" class="nav-link px-2">Вакансии</a></li>
    </ul>

    <div class="col-md-3 text-end">
        @if(Auth::check())
            <a href="{{ route('profile.edit') }}" class="btn btn-light me-2">{{ Auth::user()->name }}</a>
            <!-- Authentication -->
            <form method="POST" action="{{ route('logout') }}" class="logout_form">
                @csrf
                <button onclick="event.preventDefault(); this.closest('form').submit();" class="btn btn-primary">{{ __('Выйти') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-light me-2">Вход</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Регистрация</a>
        @endif
    </div>
</header>
<div style="height: 110px;"></div>
