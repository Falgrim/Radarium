<!DOCTYPE html>
<html data-bs-theme="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Radarium') }}</title>

    <link rel="stylesheet" href="{{ asset('v2/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/Mont.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/Navbar-Centered-Links-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/styles.css') }}?v=1.3">

    <script type="application/javascript" src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
</head>
    <body>
        <x-header />

        {{ $slot }}

        <x-footer />

        <x-modals :$userRoleList />

        <script src="{{ asset('v2/bootstrap/js/bootstrap.min.js') }}"></script>
        <script src="{{ asset('v2/js/script.js') }}"></script>

        @stack('scripts')
        <script>
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>
