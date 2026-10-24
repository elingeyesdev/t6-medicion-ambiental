<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/** Caché de capturas, no de resultados SPI. Conserva originales aun al renovarlos. */
final class CapturadorPrecipitacion
{
    public const VIGENCIA_SEGUNDOS = 21600;

    public const VIGENCIA_INCOMPLETA_SEGUNDOS = 900;

    public function __construct(private readonly ClienteOpenMeteoPropuesto $cliente, private readonly RepositorioSeriesPrecipitacion $series) {}

    public function ejecutar(string $muestra, mixed $longitud, mixed $latitud, string $desde, string $hasta, bool $renovar = false): array
    {
        $s = $this->cliente->solicitud($muestra, $longitud, $latitud, $desde, $hasta, DateTimeImmutable::createFromInterface(now()));
        $clave = 'sequias:captura:v1:'.$s['huella'];
        $bloqueo = Cache::lock($clave.':bloqueo', 120);
        if (! $bloqueo->get()) {
            throw new SeriePrecipitacionInvalida('CAPTURA_OCUPADA', 'Ya se está obteniendo esta captura; vuelve a intentarlo.');
        }
        $temporal = null;
        try {
            if (! $renovar && ($indice = Cache::get($clave)) !== null) {
                if (! is_array($indice)) {
                    throw new SeriePrecipitacionInvalida('INDICE_CAPTURA_INVALIDO', 'El índice de captura no es utilizable; revisar antes de renovar.');
                }

                return $this->recuperar($indice, $s, true);
            }
            $descarga = $this->cliente->descargar($s);
            // El temporal contiene datos públicos; se elimina incluso si falla el almacén.
            $temporal = tempnam(sys_get_temp_dir(), 'geocruz-lluvia-');
            if ($temporal === false || @file_put_contents($temporal, $descarga['crudo']) !== strlen($descarga['crudo'])) {
                throw new SeriePrecipitacionInvalida('ALMACEN_NO_DISPONIBLE', 'No se pudo preparar el archivo temporal de la captura.');
            }
            $version = $this->series->preparar($temporal, $descarga['procedencia']);
            $vigencia = $version['informe']['aptoParaAgregacionMensual'] ? self::VIGENCIA_SEGUNDOS : self::VIGENCIA_INCOMPLETA_SEGUNDOS;
            $indice = ['identificador' => $version['identificador'], 'http' => $descarga['http'],
                'expiraEn' => now()->addSeconds($vigencia)->toIso8601ZuluString()];
            if (! Cache::put($clave, $indice, $vigencia)) {
                throw new SeriePrecipitacionInvalida('CAPTURA_NO_INDEXADA', 'La serie se conservó con ID '.$version['identificador'].', pero no se pudo guardar su índice de reutilización.');
            }

            return $this->recuperar($indice, $s, false);
        } finally {
            if (is_string($temporal) && is_file($temporal)) {
                @unlink($temporal);
            }
            $bloqueo->release();
        }
    }

    private function recuperar(array $indice, array $solicitud, bool $cache): array
    {
        if (! is_string($indice['identificador'] ?? null) || ! is_string($indice['expiraEn'] ?? null) || ! is_array($indice['http'] ?? null)) {
            throw new SeriePrecipitacionInvalida('INDICE_CAPTURA_INVALIDO', 'El índice de captura no es utilizable; revisar el almacén antes de renovar.');
        }
        $v = $this->series->leer($indice['identificador']);
        $p = $v['procedencia'];
        if ($p['urlOrigen'] !== $solicitud['url'] || $p['muestra'] !== $solicitud['muestra']
            || $p['producto'] !== 'ERA5' || $p['proveedor'] !== 'Open-Meteo' || $p['naturaleza'] !== 'reanalisis'
            || $p['tipoEvidencia'] !== 'captura_externa' || $p['periodo'] !== $solicitud['periodo']
            || $p['coordenadaSolicitada'] !== $solicitud['coordenadaSolicitada']) {
            throw new SeriePrecipitacionInvalida('INDICE_CAPTURA_INVALIDO', 'La versión del índice no corresponde a esta solicitud.');
        }

        return ['estado' => 'captura_candidata_pendiente_revision', 'tipoObtencion' => $cache ? 'cache_captura' : 'consulta_proveedor',
            'esConsultaNueva' => ! $cache, 'expiraEn' => $indice['expiraEn'], 'httpOriginal' => $indice['http'],
            'version' => $v, 'advertencias' => ['Captura de una celda de reanálisis; no constituye SPI ni una estación observada.',
                'No acredita cobertura municipal ni activa el módulo público; propuesta de #35 pendiente de revisión.']];
    }
}
