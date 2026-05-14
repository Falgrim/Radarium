<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Radarium') }}</title>

        @vite('resources/js/app.js')

        <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
        <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}">

        <script type="application/javascript" src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
        <script type="application/javascript" src="{{ asset('js/select2.full.min.js') }}"></script>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <x-header />

        {{ $slot }}

        <x-footer />

        @stack('scripts')
        <x-session-idle-timeout />
    </body>
</html>
