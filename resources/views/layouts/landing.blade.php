<!DOCTYPE html>
<html data-bs-theme="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Radarium') }}</title>


    <link rel="stylesheet" href="{{ asset('v2/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/Mont.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/fonts/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/fonts/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/fonts/fontawesome5-overrides.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/Navbar-Centered-Links-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/styles.css') }}?v=1.1">
</head>

<body>
<header>
    <div class="container">
        <nav class="navbar navbar-expand-xl">
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
                        <li class="nav-item"><a class="nav-link active" href="{{ route('index') }}#industries">Отрасли</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#why">Почему Radarium</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#buy_tariff">Тарифы</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#authors">Авторы</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#faq">Как работает?</a></li>
                    </ul>
                    <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                        <img src="{{ asset('v2/img/icon-profile.svg') }}"><span>Войти</span>
                    </button>
                </div>
            </div>
        </nav>
    </div>
</header>

{{ $slot }}

<footer>
    <div class="container">
        <nav class="navbar">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ route('index') }}"><img src="{{ asset('v2/img/logo-radarium-w.svg') }}"></a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link active" href="{{ route('index') }}#industries">Отрасли</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#why">Почему Radarium</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#buy_tariff">Тарифы</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#authors">Авторы</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#faq">Как работает?</a></li>
                    </ul>
                    <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                        <img src="{{ asset('v2/img/icon-profile.svg') }}"><span>Войти</span>
                    </button>
                </div>
            </div>
        </nav>
    </div>
</footer>

<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-registr">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Вход в личный кабинет</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="registration-form">
                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="formCheck-1" name="remember">
                            <label class="form-check-label" for="formCheck-1">Запомнить меня</label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-color btn-accent" type="submit">Вход</button>
                            @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}">Забыли пароль?</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="ftr-img"><img src="{{ asset('v2/img/icon-man-grey.svg') }}"></div>
                <div>
                    <h4 class="footer-title">У вас нет аккаунта?</h4>
                    <p><button class="btn btn-trans btn-link" type="submit" data-bs-toggle="modal" data-bs-target="#rd-account">Пройдите быструю регистрацию</button></p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-account">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Регистрация аккаунта</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="registration-form">
                        <input class="form-control" type="text" name="name" placeholder="ФИО" required="required">
                        <input class="form-control" type="text" name="phone" placeholder="+79991112233" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="from_company" value="1" id="formCheck-2">
                            <label class="form-check-label" for="formCheck-2">Представляю компанию</label>
                        </div>
                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <select class="form-select" name="user_role_id">
                            @foreach ($userRoleList as $userRole)
                                <option value="{{ $userRole['id'] }}">{{ $userRole['value'] }}</option>
                            @endforeach
                        </select>
                        <hr>
                        <h4>Пароль</h4>
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Подтвердите пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="user_agree" value="1" id="formCheck-user_agree" required>
                            <label class="form-check-label" for="formCheck-user_agree"><a href="{{ asset('storage/documents/user-agreement.pdf') }}" target="_blank">{{ __('С пользовательским соглашением ознакомлен') }}</a></label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-normal btn-color" type="submit">Зарегистрироваться</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-registr" role="dialog" tabindex="-1" id="rd-drive">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-accent">
                <h4 class="modal-title">Получи тест-драйв</h4>
                <button class="btn btn-primary btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"><img src="{{ asset('v2/img/icon-close-white.svg') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-4">Тест-драйв доступен для&nbsp;всех новых пользователей при&nbsp;входе в&nbsp;систему. Вы можете попробовать функции поиска, а&nbsp;также иметь доступ к&nbsp;открытию контактов и&nbsp;карточек исполнителей для просмотра функций системы. Пожалуйста, зарегистрируйтесь</p>
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="registration-form">
                        <input class="form-control" type="text" name="name" placeholder="ФИО" required="required">
                        <input class="form-control" type="text" name="phone" placeholder="+79991112233" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="from_company" value="1" id="formCheck-2">
                            <label class="form-check-label" for="formCheck-2">Представляю компанию</label>
                        </div>
                        <input class="form-control" type="email" name="email" placeholder="Почта" required="required">
                        <select class="form-select" name="user_role_id">
                            @foreach ($userRoleList as $userRole)
                                <option value="{{ $userRole['id'] }}">{{ $userRole['value'] }}</option>
                            @endforeach
                        </select>
                        <hr>
                        <h4>Пароль</h4>
                        <input class="form-control" type="password" name="password" placeholder="Пароль" required="required">
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Подтвердите пароль" required="required">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="user_agree" value="1" id="formCheck-user_agree2" required>
                            <label class="form-check-label" for="formCheck-user_agree2"><a href="{{ asset('storage/documents/user-agreement.pdf') }}" target="_blank">{{ __('С пользовательским соглашением ознакомлен') }}</a></label>
                        </div>
                        <div class="box-btn-line">
                            <button class="btn btn-normal btn-color" type="submit">Зарегистрироваться</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('v2/bootstrap/js/bootstrap.min.js') }}"></script>

@stack('scripts')

</body>
</html>
