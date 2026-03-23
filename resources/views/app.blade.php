<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            (function() {
                const match = document.cookie.match(/(?:^|;\s*)ui_motion=([^;]+)/);
                const motionPreference = match ? decodeURIComponent(match[1]) : 'standard';

                document.documentElement.classList.add('dark');

                if (motionPreference === 'reduced') {
                    document.documentElement.classList.add('motion-reduced-ui');
                }
            })();
        </script>

        <style>
            html {
                background-color: #0c0d10;
                color-scheme: dark;
            }
        </style>

        <!--suppress HtmlUnknownAttribute -->
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=pfm-1">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v=pfm-1">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=pfm-1">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=pfm-1">

        @routes
        @vite('resources/js/app.ts')
        @if (!app()->environment('testing'))
            @vite(["resources/js/pages/{$page['component']}.vue"])
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
