<!DOCTYPE html>
<html data-bs-theme="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Radarium') }}</title>
    
    
   <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PVGP4GVX');</script>
<!-- End Google Tag Manager -->


    <link rel="stylesheet" href="{{ asset('v2/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/Mont.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/fonts/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/fonts/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/slimselect.css') }}?v=1.5">
    <link rel="stylesheet" href="{{ asset('v2/css/Navbar-Centered-Links-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/styles.css') }}?v=1.17">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    <script type="application/javascript" src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    <script type="application/javascript" src="{{ asset('v2/js/jquery.mask.min.js') }}"></script>
</head>

<body>
    
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PVGP4GVX"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    
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
                @if(Auth::check())
                    <div class="collapse navbar-collapse" id="navcol-3">
                        <ul class="navbar-nav mx-auto">
                            <li class="nav-item"><a class="nav-link" href="{{ route('catalog.specialists') }}">Перейти к поиску</a></li>
                        </ul>
                    </div>
                    <div class="client-id" style="display: block;">
                        <a href="{{ route('profile.edit') }}">
                            <span>{{ Auth::user()->name }}&nbsp;</span>
                            <img src="{{ asset('v2/img/icon-client-id.svg') }}">
                        </a>
                    </div>
                @else
                    <div class="collapse navbar-collapse" id="navcol-3">
                        <ul class="navbar-nav mx-auto">
                            <li class="nav-item"><a class="nav-link active" href="{{ route('index') }}#industries">Отрасли</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#why">Почему Radarium</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#tarifs">Тарифы</a></li>
                            <li class="nav-item" hidden=""><a class="nav-link" href="{{ route('index') }}#author">Авторы</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#howto">Как работает?</a></li>
                        </ul>
                        <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                            <img src="{{ asset('v2/img/icon-profile.svg') }}"><span>Войти</span>
                        </button>
                    </div>
                @endif
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

                @if(Auth::check())
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="{{ route('catalog.specialists') }}">Перейти к поиску</a></li>
                    </ul>
                </div>
                @else
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link active" href="{{ route('index') }}#industries">Отрасли</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#why">Почему Radarium</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#tarifs">Тарифы</a></li>
                        <li class="nav-item" hidden=""><a class="nav-link" href="{{ route('index') }}#author">Авторы</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('index') }}#howto">Как работает?</a></li>
                    </ul>
                    <button class="btn btn-profile" type="button" data-bs-toggle="modal" data-bs-target="#rd-registr">
                        <img src="{{ asset('v2/img/icon-profile.svg') }}"><span>Войти</span>
                    </button>
                </div>
                @endif
            </div>
        </nav>
    </div>
</footer>

<script src="{{ asset('v2/bootstrap/js/bootstrap.min.js') }}"></script>
<x-modals :$userRoleList />
<script src="{{ asset('v2/js/slimselect.min.js') }}"></script>
<script src="{{ asset('v2/js/script.js') }}?v=1.12"></script>

@stack('scripts')

</body>
</html>
