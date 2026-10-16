<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta as Parametros;
use App\Services\FocosCalor\ConsultaFocos;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\FiltroTerritorialInvalido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TerritoriosController
{
    public function index(Request $r, CatalogoTerritorial $catalogo): JsonResponse
    {
        $p = Parametros::leer($r, ['tipo', 'padre', 'pagina', 'tamanioPagina']);
        $pagina = Parametros::entero($p, 'pagina', 1, 1000000);
        $tamanio = Parametros::entero($p, 'tamanioPagina', 100, 500);
        $todos = $catalogo->listar($p['tipo'] ?? null, $p['padre'] ?? null);

        return response()->json([
            'versionContrato' => config('focos.version_contrato'),
            'datos' => array_slice($todos, ($pagina - 1) * $tamanio, $tamanio),
            'catalogo' => ['fuente' => 'hdx_codab_bol_v02', 'version' => $catalogo->metadatos()['version'], 'estado' => $catalogo->metadatos()['estado'], 'fechaActualizacion' => null, 'fechaGeometrias' => '2013-01-01', 'licencia' => 'CC BY 3.0 IGO', 'atribucion' => 'Ministerio de Desarrollo Rural y Tierras; OCHA FIS / HDX. Bolivia COD-AB v02.'],
            'consulta' => ['tipo' => $p['tipo'] ?? null, 'padre' => $p['padre'] ?? null, 'fechaConsulta' => now()->toIso8601ZuluString()],
            'paginacion' => ['pagina' => $pagina, 'tamanioPagina' => $tamanio, 'total' => count($todos), 'siguiente' => $pagina * $tamanio < count($todos) ? $pagina + 1 : null, 'versionConjunto' => $catalogo->metadatos()['version']],
        ])->header('Cache-Control', 'no-store');
    }

    public function limite(Request $r, string $identificador, CatalogoTerritorial $catalogo, ConsultaFocos $consulta): JsonResponse
    {
        Parametros::leer($r, []);
        try {
            $t = $catalogo->buscar($identificador);
        } catch (FiltroTerritorialInvalido) {
            throw new ErrorConsulta('TERRITORIO_NO_ENCONTRADO', 'Territorio no encontrado.', 404);
        }
        $limite = $catalogo->limite($identificador);

        return response()->json([
            'versionContrato' => config('focos.version_contrato'), 'territorio' => $consulta->territorio($t), 'limite' => $limite,
            'procedencia' => ['fuente' => 'hdx_codab_bol_v02', 'version' => $catalogo->metadatos()['version'], 'estado' => $catalogo->metadatos()['estado'], 'fechaActualizacion' => null, 'fechaGeometrias' => '2013-01-01', 'licencia' => 'CC BY 3.0 IGO', 'referencia' => 'https://data.humdata.org/dataset/cod-ab-bol', 'atribucion' => 'Ministerio de Desarrollo Rural y Tierras; OCHA FIS / HDX. Bolivia COD-AB v02.'],
        ])->header('Cache-Control', 'no-store');
    }
}
