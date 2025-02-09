<header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom text-bg-dark">
    <div class="col-md-3 mb-2 mb-md-0">
        <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none">
            Лого
        </a>
    </div>

    <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
        <li><a href="{{ route('index') }}" class="nav-link px-2">Главная</a></li>
        <li><a href="{{ route('catalog.specialists') }}" class="nav-link px-2">Специалисты</a></li>
        <li><a href="{{ route('catalog.companyjobs') }}" class="nav-link px-2">Вакансии</a></li>
    </ul>

    <div class="col-md-3 text-end">
        @if(Auth::check())
            <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary me-2">{{ Auth::user()->name }}</a>
            <!-- Authentication -->
            <form method="POST" action="{{ route('logout') }}" class="logout_form">
                @csrf
                <button onclick="event.preventDefault(); this.closest('form').submit();" class="btn btn-primary">{{ __('Выйти') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-primary me-2">Вход</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Регистрация</a>
        @endif
    </div>
</header>
