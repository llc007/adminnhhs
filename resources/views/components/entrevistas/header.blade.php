@props(['titulo', 'subtitulo', 'icono' => 'calendar'])

<x-header :titulo="$titulo" :subtitulo="$subtitulo" :icono="$icono">
    {{ $slot }}
</x-header>
