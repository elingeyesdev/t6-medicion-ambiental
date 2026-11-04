<?php

namespace App\Services\Mapas\Radar;

use DateTimeImmutable;
use UnexpectedValueException;

final class IncorporarGfs
{
    public const CAPAS = ['temperatura', 'humedad', 'presion', 'visibilidad', 'nieve', 'lluvia_intensidad', 'lluvia', 'viento'];

    public const ENERGIA = ['radiacion_solar', 'evaporacion_potencial'];

    public function ejecutar(string $lote): array
    {
        $raiz = config('capas.directorio');
        if (! is_string($raiz) || ! is_dir($raiz) || realpath($lote) === realpath($raiz)) {
            throw new UnexpectedValueException('Revisa los directorios del lote y de publicación.');
        }

        return BloqueoRaster::ejecutar($raiz, function () use ($raiz, $lote): array {
            $manifest = $this->json($lote.'/actualizacion.json');
            $ciclo = DateTimeImmutable::createFromFormat('!YmdH', $manifest['ciclo'] ?? '', new \DateTimeZone('UTC'));
            if (($manifest['version'] ?? null) !== 1 || ($manifest['datos_controlados'] ?? null) !== false || ! $ciclo ||
                $ciclo->format('YmdH') !== $manifest['ciclo'] || ! in_array($ciclo->format('H'), ['00', '06', '12', '18'], true)) {
                throw new UnexpectedValueException('El lote no acredita un ciclo GFS real.');
            }
            $receta = $this->json($lote.'/receta.json');
            $perfil = $manifest['perfil'] ?? 'ambiental';
            $capas = match ($perfil) {
                'ambiental' => self::CAPAS,
                'energia' => self::ENERGIA,
                default => throw new UnexpectedValueException('Perfil de publicación desconocido.'),
            };
            $publicador = new PublicadorRaster;
            $publicador->publicar($lote, $receta);
            $nuevos = (new CatalogoRaster)->validar($this->json($lote.'/catalogo.json'));
            $esperados = [];
            foreach ($capas as $capa) {
                foreach ([3, 6] as $plazo) {
                    $esperados[$capa.'/'.$ciclo->modify('+'.$plazo.' hours')->format('Y-m-d\TH:i:s\Z')] = true;
                }
            }
            foreach ($nuevos as $c) {
                $clave = $c['capa'].'/'.$c['instante'];
                $t = $c['intervalo'] ?? [];
                if (! isset($esperados[$clave]) || ($t['ciclo'] ?? null) !== $ciclo->format('Y-m-d\TH:i:s\Z') ||
                    ($t['instante'] ?? null) !== $c['instante'] || ! in_array($t['fin_horas'] ?? null, [3, 6], true) ||
                    $ciclo->modify('+'.$t['fin_horas'].' hours')->format('Y-m-d\TH:i:s\Z') !== $c['instante'] ||
                    ($c['capa'] === 'lluvia' && ($t['inicio_horas'] ?? null) !== 0)) {
                    throw new UnexpectedValueException('El lote debe contener las capas del perfil en los plazos 3 y 6 del mismo ciclo.');
                }
                if ($perfil === 'energia') {
                    $promedio = $c['capa'] === 'radiacion_solar';
                    $desde = $promedio ? 0 : $t['fin_horas'];
                    if (($c['unidad'] ?? null) !== 'W/m²' || ($t['tipo_paso'] ?? null) !== ($promedio ? 'avg' : 'instant') ||
                        ($t['inicio_horas'] ?? null) !== $desde ||
                        ($t['desde'] ?? null) !== $ciclo->modify('+'.$desde.' hours')->format('Y-m-d\TH:i:s\Z') ||
                        ($t['hasta'] ?? null) !== $c['instante']) {
                        throw new UnexpectedValueException('La energía requiere unidades y período explícitos; no se acepta como UV ni agua perdida.');
                    }
                }
                unset($esperados[$clave]);
            }
            if ($esperados) {
                throw new UnexpectedValueException('El lote de capas está incompleto.');
            }
            $catalogo = new CatalogoRaster;
            $anteriores = file_exists($raiz.'/catalogo.json') ? $catalogo->validar($this->json($raiz.'/catalogo.json')) : [];
            $unidos = array_column($anteriores, null, 'id');
            $fechas = [];
            foreach ($anteriores as $c) {
                $fechas[$c['capa'].'/'.$c['instante']] = $c['id'];
            }
            $agregados = 0;
            foreach ($nuevos as $c) {
                $clave = $c['capa'].'/'.$c['instante'];
                if (isset($unidos[$c['id']])) {
                    if ($unidos[$c['id']]['sha256'] !== $c['sha256']) {
                        throw new UnexpectedValueException('Una imagen existente tiene contenido diferente.');
                    }

                    continue;
                }
                if (isset($fechas[$clave])) {
                    throw new UnexpectedValueException('Ya existe otro producto en esa capa y fecha; se conserva para revisión.');
                }
                $c['actualizacion_gfs'] = $manifest['ciclo'];
                $unidos[$c['id']] = $c;
                $agregados++;
            }
            // Se valida el conjunto antes de copiar: ningún conflicto sustituye el catálogo activo.
            foreach ($nuevos as $c) {
                $origen = ArchivoRaster::ruta($lote, $c['archivo']);
                $carpeta = ArchivoPublicacionRaster::directorio($raiz, dirname($c['archivo']));
                $destino = $carpeta.'/'.basename($c['archivo']);
                if (file_exists($destino) || is_link($destino)) {
                    if (hash_file('sha256', ArchivoRaster::ruta($raiz, $c['archivo'])) !== $c['sha256']) {
                        throw new UnexpectedValueException('El nombre de una imagen guardada ya está ocupado.');
                    }
                } else {
                    $temporal = tempnam($carpeta, 'imagen-');
                    if ($temporal === false) {
                        throw new UnexpectedValueException('No se pudo guardar una imagen nueva.');
                    }
                    try {
                        $bytes = ArchivoRaster::leer($origen);
                        if (hash('sha256', $bytes) !== $c['sha256']) {
                            throw new UnexpectedValueException('El insumo cambió después de validarse.');
                        }
                        if (file_put_contents($temporal, $bytes) !== strlen($bytes) || ! rename($temporal, $destino)) {
                            throw new UnexpectedValueException('No se pudo guardar la imagen completa.');
                        }
                    } finally {
                        if (file_exists($temporal)) {
                            unlink($temporal);
                        }
                    }
                }
            }
            foreach ($unidos as $c) {
                $bytes = ArchivoRaster::leer(ArchivoRaster::ruta($raiz, $c['archivo']));
                if (hash('sha256', $bytes) !== $c['sha256'] || ArchivoRaster::dimensiones($bytes) !== [$c['ancho'], $c['alto']]) {
                    throw new UnexpectedValueException('Cambió una imagen anterior; se conserva su catálogo para revisión.');
                }
                ArchivoPublicacionRaster::guardar($raiz, $c);
            }
            $limite = max(2, min(48, (int) config('capas.actualizacion.fechas_por_capa', 48)));
            usort($unidos, fn ($a, $b) => strcmp($b['instante'], $a['instante']));
            $cuentas = [];
            $activos = array_values(array_filter($unidos, function ($c) use (&$cuentas, $limite) {
                if (! isset($c['actualizacion_gfs'])) {
                    return true; // Las cargas manuales no se retiran automáticamente.
                }
                $cuentas[$c['capa']] = ($cuentas[$c['capa']] ?? 0) + 1;

                return $cuentas[$c['capa']] <= $limite;
            }));
            $cantidad = $publicador->publicar($raiz, ['version' => 1, 'cuadros' => $activos]);

            return ['estado' => 'publicado', 'ciclo' => $manifest['ciclo'], 'nuevos' => $agregados, 'activos' => $cantidad, 'archivados' => count($unidos) - $cantidad];
        });
    }

    private function json(string $ruta): array
    {
        if (! is_file($ruta) || is_link($ruta) || filesize($ruta) > 2097152) {
            throw new UnexpectedValueException('Documento de publicación ausente o inválido.');
        }
        $datos = json_decode(file_get_contents($ruta), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($datos)) {
            throw new UnexpectedValueException('Documento de publicación inválido.');
        }

        return $datos;
    }
}
