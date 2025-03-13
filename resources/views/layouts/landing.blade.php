<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Исполнители') }}</title>

        @vite('resources/js/app.js')

        <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    </head>
    <body>
        <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom text-bg-dark">
            <div class="col-md-3 mb-2 mb-md-0">
                <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none">
                    Лого
                </a>
            </div>

            <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
                <li>Radarium</li>
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


        {{ $slot }}

        <x-footer />

        @stack('scripts')
    </body>
</html>
