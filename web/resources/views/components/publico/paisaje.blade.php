<section {{ $attributes->class(['paisaje-geocruz']) }}>
    <img class="paisaje-foto" src="{{ asset('img/amboro/selva-amboro.jpg') }}" alt="Selva del Parque Nacional Amboró, Santa Cruz" width="1280" height="960" fetchpriority="high">
    {{ $slot }}
    <div class="paisaje-lugar"><x-panel.icono nombre="map-pin" /><span>Parque Nacional Amboró</span></div>
</section>
