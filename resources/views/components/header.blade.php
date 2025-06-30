@php
    $contactsLimit = Auth::check() ? Auth::user()->getLeftContacts() : [];
@endphp

<header>
    <div class="container">
        <nav class="navbar navbar-expand-xl client-in">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ route('index') }}">
                    <img src="{{ asset('v2/img/logo-radarium-b.svg') }}">
                </a>
                <button data-bs-toggle="collapse" class="navbar-toggler" data-bs-target="#navcol-3">
                    <span class="visually-hidden">Toggle navigation</span>
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navcol-3">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="{{ route('catalog.specialists') }}">Проектирование</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('catalog.builders') }}">Строительство</a></li>
                    </ul>
                    <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                        <img src="{{ asset('v2/img/icon-profile.svg') }}"><span>Войти</span>
                    </button>
                </div>
                @if(Auth::check())
                <div class="client-id">
                    <a href="{{ route('profile.edit') }}">
                        <span>{{ Auth::user()->name }}&nbsp;</span>
                        <img src="{{ asset('v2/img/icon-client-id.svg') }}">
                    </a>
                    @if (!Illuminate\Support\Facades\Route::is('catalog.specialists') AND !Illuminate\Support\Facades\Route::is('catalog.specialist.view'))
                    <form method="POST" action="{{ route('logout') }}" class="logout_form">
                        @csrf
                        <button onclick="event.preventDefault(); this.closest('form').submit();" class="btn btn-primary">{{ __('Выйти') }}</button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </nav>
    </div>
</header>
