@extends('layouts.app')
@section('titulo', 'Niveles ambientales')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido">
<header class="alertas-cabecera"><div><h1>Niveles <span>Ambientales</span></h1><p>Consulta los umbrales de referencia para cada variable ambiental.</p></div><span class="alertas-etiqueta">Umbrales</span></header>
<nav class="alertas-pestanas" aria-label="Alertas y notificaciones"><a href="{{ route('alertas') }}">Historial de alertas</a><a href="{{ route('umbrales') }}" aria-current="page">Niveles ambientales</a><a href="{{ route('avisos') }}">Mis avisos</a></nav>
<p class="alertas-nota">Son los niveles de referencia del sistema. No sustituyen un informe oficial.</p>
@foreach($catalogo['metricas'] as $clave => $niveles)
<section class="card mt-4"><div class="card-header"><h2>{{ $metricas[$clave]['nombre'] }}</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Nivel</th><th>Desde</th><th>Hasta</th><th>Unidad</th></tr></thead><tbody>@foreach($niveles as $n)<tr><th>{{ $n['nombre'] }}</th><td>{{ $n['minimo'] }}</td><td>{{ $n['maximo'] }}</td><td>{{ $metricas[$clave]['unidad'] }}</td></tr>@endforeach</tbody></table></div></section>
@endforeach</div>
@endsection
