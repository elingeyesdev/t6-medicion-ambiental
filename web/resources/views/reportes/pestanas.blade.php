<nav class="rep-pestanas" aria-label="Área de reportes">
    <a href="{{ route('reportes', ['vista' => 'dashboard']) }}" @if($vistaActiva === 'dashboard') aria-current="page" @endif>Dashboard Global</a>
    <a href="{{ route('reportes', ['vista' => 'atmosfera']) }}" @if($vistaActiva === 'atmosfera') aria-current="page" @endif>Pronóstico Atmosférico</a>
    <a href="{{ route('reportes', ['vista' => 'historial']) }}" @if($vistaActiva === 'historial') aria-current="page" @endif>Historial y Simulaciones</a>
    <a href="{{ route('plantillas') }}" @if($vistaActiva === 'plantillas') aria-current="page" @endif>Plantillas</a>
</nav>
