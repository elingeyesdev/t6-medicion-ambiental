<svg class="sim-icono" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icono)
        @case('aqi')<path d="M4 6h16M4 10h16M4 14h16M4 18h16" />@break
        @case('ica')@case('humedad')<path d="M12 3c-1 5-7 8-7 12a7 7 0 0 0 14 0c0-4-6-7-7-12Z" />@break
        @case('ruido')<path d="m11 5-5 4H2v6h4l5 4V5ZM16 8a6 6 0 0 1 0 8M19 5a10 10 0 0 1 0 14" />@break
        @case('temperatura')<path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0ZM12 7v9" />@break
        @case('iniciar')<path d="m8 5 11 7-11 7V5Z" />@break
        @case('pausar')<path d="M8 5v14M16 5v14" />@break
        @case('detener')<rect x="5" y="5" width="14" height="14" rx="2" />@break
        @case('reiniciar')<path d="M3 10a9 9 0 1 1 2 8M3 4v6h6" />@break
        @default<path d="M9 3h6M10 3v6l-6 10a1.3 1.3 0 0 0 1 2h14a1.3 1.3 0 0 0 1-2L14 9V3M8 14h8" />
    @endswitch
</svg>
