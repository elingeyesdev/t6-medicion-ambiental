<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use DateTimeImmutable;
use DateTimeZone;

/** Versiones locales sin sobreescritura; no es la caché operativa ni un servicio público. */
final class RepositorioSeriesPrecipitacion
{
    public function __construct(
        private readonly string $directorio,
        private readonly LectorJsonLocal $lector,
        private readonly ValidadorSeriePrecipitacion $validador,
        private readonly ProcedenciaSeriePrecipitacion $procedencias,
    ) {}

    public function preparar(string $archivo, array $declaracion): array
    {
        $p = $this->procedencias->normalizar($declaracion);
        $datos = $this->lector->leer($archivo);
        $informe = $this->comprobar($datos, $p);
        $id = $this->identificador($p);
        if (! is_dir($this->directorio) && ! @mkdir($this->directorio, 0700, true) && ! is_dir($this->directorio)) {
            $this->almacenNoDisponible();
        }
        $raiz = realpath($this->directorio);
        if ($raiz === false) {
            $this->almacenNoDisponible();
        }
        $bloqueo = @fopen($raiz.'/.bloqueo', 'c+b');
        if ($bloqueo === false) {
            $this->almacenNoDisponible();
        }
        if (! flock($bloqueo, LOCK_EX | LOCK_NB)) {
            fclose($bloqueo);
            throw new SeriePrecipitacionInvalida('ALMACEN_OCUPADO', 'Otra preparación está escribiendo; vuelve a intentarlo.');
        }
        $temporal = null;
        try {
            clearstatcache(true, $raiz.'/'.$id);
            if (file_exists($raiz.'/'.$id)) {
                return ['reutilizada' => true, ...$this->leer($id)];
            }
            $temporal = $raiz.'/.preparando-'.bin2hex(random_bytes(12));
            if (! @mkdir($temporal, 0700)) {
                $this->almacenNoDisponible();
            }
            $version = ['versionAlmacenamiento' => 1, 'identificador' => $id,
                'preparadoEn' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
                'procedencia' => $p, 'informe' => $informe];
            $version['checksumManifiesto'] = hash('sha256', $this->json($version));
            $manifiesto = $this->json($version)."\n";
            if (@file_put_contents($temporal.'/captura.json', $datos['crudo']) !== $datos['bytes']
                || @file_put_contents($temporal.'/manifiesto.json', $manifiesto) !== strlen($manifiesto)
                || ! $this->publicar($temporal, $raiz.'/'.$id)) {
                $this->almacenNoDisponible();
            }
            $temporal = null;

            return ['reutilizada' => false, ...$this->leer($id)];
        } finally {
            if ($temporal !== null && is_dir($temporal)) {
                // Solo archivos con nombres fijos dentro del temporal creado por esta operación.
                foreach (['captura.json', 'manifiesto.json'] as $nombre) {
                    if (is_file($temporal.'/'.$nombre)) {
                        @unlink($temporal.'/'.$nombre);
                    }
                }
                @rmdir($temporal);
            }
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function leer(string $id): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $id)) {
            throw new SeriePrecipitacionInvalida('IDENTIFICADOR_INVALIDO', 'Indica el identificador SHA-256 de una serie preparada.');
        }
        $ruta = $this->directorio.'/'.$id;
        if (! is_dir($ruta)) {
            throw new SeriePrecipitacionInvalida('SERIE_NO_ENCONTRADA', 'No existe una versión preparada con ese identificador.');
        }
        try {
            $d = $this->lector->leer($ruta.'/manifiesto.json', 4 * 1024 * 1024)['datos'];
            if (count($d) !== 6 || ($d['versionAlmacenamiento'] ?? null) !== 1 || ($d['identificador'] ?? null) !== $id
                || ! is_array($d['procedencia'] ?? null) || ! is_array($d['informe'] ?? null)) {
                $this->corrupta();
            }
            $sinChecksum = $d;
            unset($sinChecksum['checksumManifiesto']);
            if (! is_string($d['checksumManifiesto'] ?? null)
                || ! hash_equals($d['checksumManifiesto'], hash('sha256', $this->json($sinChecksum)))) {
                $this->corrupta();
            }
            $this->procedencias->instante($d['preparadoEn'] ?? null);
            $p = $this->procedencias->normalizar($d['procedencia']);
            if ($this->identificador($p) !== $id) {
                $this->corrupta();
            }
            $datos = $this->lector->leer($ruta.'/captura.json');
            $informe = $this->comprobar($datos, $p);
            if ($d['informe'] !== $informe) {
                $this->corrupta();
            }

            return ['integridad' => 'verificada', ...$d];
        } catch (SeriePrecipitacionInvalida) {
            $this->corrupta();
        }
    }

    private function comprobar(array $archivo, array $p): array
    {
        if (! hash_equals($p['sha256'], $archivo['sha256'])) {
            throw new SeriePrecipitacionInvalida('HASH_NO_COINCIDE', 'La captura no coincide con el SHA-256 declarado.');
        }
        $d = $archivo['datos'];
        if (array_key_exists('latitude', $d) || array_key_exists('longitude', $d)) {
            foreach (['longitude', 'latitude'] as $i => $campo) {
                $valor = $d[$campo] ?? null;
                if ((! is_int($valor) && ! is_float($valor)) || ! is_finite((float) $valor)
                    || abs($valor - $p['coordenadaCelda'][$i]) > 1e-9) {
                    throw new SeriePrecipitacionInvalida('CELDA_INCONSISTENTE', 'La coordenada devuelta no coincide con la celda declarada.');
                }
            }
        }

        return $this->validador->validar($d, $p['periodo']['desde'], $p['periodo']['hastaIncluido']);
    }

    private function identificador(array $procedencia): string
    {
        return hash('sha256', $this->json(['versionAlmacenamiento' => 1, 'procedencia' => $procedencia]));
    }

    private function publicar(string $temporal, string $destino): bool
    {
        // En Windows un lector del sistema puede retener brevemente un archivo recién escrito.
        // Espera local acotada (máximo 150 ms); nunca sobrescribir un destino existente.
        for ($intento = 0; $intento < 4; $intento++) {
            clearstatcache(true, $destino);
            if (file_exists($destino)) {
                return false;
            }
            if (@rename($temporal, $destino)) {
                return true;
            }
            if (PHP_OS_FAMILY !== 'Windows' || $intento === 3) {
                return false;
            }
            usleep(50000);
        }

        return false;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function almacenNoDisponible(): never
    {
        throw new SeriePrecipitacionInvalida('ALMACEN_NO_DISPONIBLE', 'No se pudo publicar la serie en el almacén local.');
    }

    private function corrupta(): never
    {
        throw new SeriePrecipitacionInvalida('SERIE_CORRUPTA', 'La versión guardada está incompleta, modificada o no coincide con su validación. No se reutilizó.');
    }
}
