@props(['nombre' => 'radar'])
<svg {{ $attributes->merge(['class' => 'enviro-icon']) }} width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($nombre)
@case('arrow')<path d="M5 12h14m-7-7 7 7-7 7"/>@break
@case('flame')<path d="M12 3c1 5 6 6 6 12a6 6 0 0 1-12 0c0-3 2-5 3-7 0 4 2 5 3 5 2-2 1-6 0-10Z"/>@break
@case('rain')<path d="M6 14a4 4 0 1 1 1-8 6 6 0 0 1 11 3 3 3 0 0 1 0 6M8 17l-1 3m6-3-1 3m6-3-1 3"/>@break
@case('cloud')<path d="M7 19a4 4 0 1 1 0-8 6 6 0 0 1 12-1 4.5 4.5 0 0 1-1 9Z"/>@break
@case('chart')<path d="M3 3v18h18M6 15l4-4 4 3 6-7m-5 0h5v5"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6"/>@break
@case('screen')<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/>@break
@case('leaf')<path d="M11 20A7 7 0 0 1 10 6C16 5 18 4 20 2c1 2 2 5 1 8 0 6-5 10-10 10ZM2 21c0-4 4-6 7-7l4-3"/>@break
@case('building')<path d="M4 22V2m16 0v20M4 6h16M4 10h16M4 14h16M4 18h16M2 22h20m-12 0V10h4v12"/>@break
@case('wind')<path d="M2 8h9a2 2 0 1 0-2-2M2 12h17a3 3 0 1 0-3-3M2 16h12a2 2 0 1 1-2 2"/>@break
@case('temperature')<path d="M10 14V5a2 2 0 0 1 4 0v9a4 4 0 1 1-4 0Zm2-6v10"/>@break
@case('drop')<path d="M12 3S5 12 5 16a7 7 0 0 0 14 0c0-4-7-13-7-13Z"/>@break
@case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/>@break
@default<circle cx="12" cy="12" r="9" stroke-dasharray="3 2" opacity=".5"/><circle cx="12" cy="12" r="5" opacity=".7"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><path d="m12 12 7-6"/>
@endswitch
</svg>
