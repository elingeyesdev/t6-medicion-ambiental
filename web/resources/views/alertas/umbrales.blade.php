@extends('layouts.app')
@section('titulo', 'Niveles ambientales')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido"><a href="{{ route('alertas') }}">Volver a alertas</a><h1 class="mt-3">Niveles ambientales</h1>
<p>Son los niveles de referencia del sistema. No sustituyen un informe oficial.</p>
@foreach($catalogo['metricas'] as $clave => $niveles)
<section class="card mt-4"><div class="card-header"><h2>{{ $metricas[$clave]['nombre'] }}</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Nivel</th><th>Desde</th><th>Hasta</th><th>Unidad</th></tr></thead><tbody>@foreach($niveles as $n)<tr><th>{{ $n['nombre'] }}</th><td>{{ $n['minimo'] }}</td><td>{{ $n['maximo'] }}</td><td>{{ $metricas[$clave]['unidad'] }}</td></tr>@endforeach</tbody></table></div></section>
@endforeach</div>
@endsection
