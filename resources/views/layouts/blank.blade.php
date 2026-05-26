<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-950 font-sans antialiased text-white selection:bg-blue-600 selection:text-white">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
