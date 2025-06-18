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
    <link rel="stylesheet" href="{{ asset('v2/css/styles.css') }}?v=1.3">
    <!--строка загрузки favcion добавлена А. Головин 1806 -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

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
                        <!--<li class="nav-item"><a class="nav-link" href="{{ route('index') }}#authors">Авторы</a></li>-->
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
                        <!--<li class="nav-item"><a class="nav-link" href="{{ route('index') }}#authors">Авторы</a></li>-->
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

<x-modals :$userRoleList />

<script src="{{ asset('v2/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('v2/bootstrap/js/script.js') }}"></script>

@stack('scripts')

</body>
</html>
