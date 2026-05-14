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
    <link rel="stylesheet" href="{{ asset('v2/css/slimselect.css') }}?v=1.5">
    <link rel="stylesheet" href="{{ asset('v2/css/Navbar-Centered-Links-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('v2/css/styles.css') }}?v={{ is_file(public_path('v2/css/styles.css')) ? filemtime(public_path('v2/css/styles.css')) : 0 }}">
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}">

    <script type="application/javascript" src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    <script type="application/javascript" src="{{ asset('js/select2.full.min.js') }}"></script>
    <script type="application/javascript" src="{{ asset('v2/js/jquery.mask.min.js') }}"></script>
</head>
    <body>
        
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PVGP4GVX"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->

        <x-header />

        {{ $slot }}

        <x-footer />

        <script src="{{ asset('v2/bootstrap/js/bootstrap.min.js') }}"></script>
        <x-modals :$userRoleList />
        <script src="{{ asset('v2/js/slimselect.min.js') }}"></script>
        <script src="{{ asset('v2/js/script.js') }}?v=1.12"></script>
        <script src="{{ asset('v2/js/star-script.js') }}?v=1.12"></script>

        @stack('scripts')
        <x-session-idle-timeout />
        <script>
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>
