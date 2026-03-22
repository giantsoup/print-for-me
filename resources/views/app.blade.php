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

        <link rel="icon" type="image/png" href="{{ asset('website-logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('website-logo.png') }}">

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
