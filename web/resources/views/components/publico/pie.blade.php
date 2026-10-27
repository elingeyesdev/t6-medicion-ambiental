@props(['fotografia' => 'amboro'])
<footer class="portada-pie">
    <span>GeoCruz · Santa Cruz, Bolivia</span>
    <details class="portada-credito">
        <summary>Sobre la fotografía</summary>
        <p>{{ $fotografia === 'lomas' ? 'Lomas de Arena' : 'Parque Nacional Amboró' }} · <x-panel.credito-foto :fotografia="$fotografia" /></p>
    </details>
</footer>
