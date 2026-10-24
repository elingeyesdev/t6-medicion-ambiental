<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Captura puntual candidata: host/producto fijos, sin rutas ni claves NASA. */
final class ClienteOpenMeteoPropuesto
{
    public const URL = 'https://archive-api.open-meteo.com/v1/archive';

    public function __construct(private readonly ValidadorSeriePrecipitacion $validador) {}

    public function solicitud(string $muestra, mixed $longitud, mixed $latitud, string $desde, string $hasta, DateTimeImmutable $ahora): array
    {
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D', $muestra)) {
            throw new SeriePrecipitacionInvalida('MUESTRA_INVALIDA', 'Usa un identificador de muestra de 1 a 80 letras, números, guion o guion bajo.');
        }
        $coordenada = [$this->coordenada($longitud, 180), $this->coordenada($latitud, 90)];
        $this->validador->validarPeriodo($desde, $hasta);
        if ($desde < '1940-01-01' || substr($hasta, 0, 7) >= $ahora->setTimezone(new DateTimeZone('UTC'))->format('Y-m')) {
            throw new SeriePrecipitacionInvalida('PERIODO_NO_DISPONIBLE', 'La captura admite meses completos desde 1940 y anteriores al mes UTC actual; la disponibilidad real depende del proveedor.');
        }
        $parametros = ['latitude' => $this->decimal($coordenada[1]), 'longitude' => $this->decimal($coordenada[0]),
            'daily' => 'precipitation_sum', 'models' => 'era5', 'timezone' => 'GMT', 'precipitation_unit' => 'mm',
            'cell_selection' => 'nearest', 'elevation' => 'nan', 'start_date' => $desde, 'end_date' => $hasta];
        $url = self::URL.'?'.http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);

        return ['muestra' => $muestra, 'coordenadaSolicitada' => $coordenada,
            'periodo' => ['desde' => $desde, 'hastaIncluido' => $hasta, 'zona' => 'UTC'],
            'parametros' => $parametros, 'url' => $url,
            'huella' => hash('sha256', json_encode(['captura-era5-v1', $muestra, $url], JSON_THROW_ON_ERROR))];
    }

    public function descargar(array $solicitud): array
    {
        $flujo = null;
        $limiteTiempo = hrtime(true) + 30_000_000_000;
        try {
            // Una sola petición, sin redirección ni reintentos automáticos en errores/cuotas.
            $respuesta = Http::acceptJson()->withUserAgent('GeoCruz-API02/0.1 (academic validation)')
                ->connectTimeout(5)->timeout(30)->withOptions(['allow_redirects' => false, 'stream' => true, 'read_timeout' => 10])
                ->get(self::URL, $solicitud['parametros']);
            $flujo = $respuesta->toPsrResponse()->getBody();
            if ($respuesta->status() !== 200) {
                throw new SeriePrecipitacionInvalida($respuesta->status() === 429 ? 'CUOTA_PROVEEDOR' : 'PROVEEDOR_NO_DISPONIBLE',
                    'Open-Meteo no devolvió una captura utilizable; no se sustituyó por datos de demostración. Reintenta más tarde.');
            }
            $crudo = '';
            while (! $flujo->eof() && strlen($crudo) <= LectorJsonLocal::MAX_BYTES) {
                if (hrtime(true) > $limiteTiempo) {
                    throw new SeriePrecipitacionInvalida('PROVEEDOR_NO_DISPONIBLE', 'La captura superó su tiempo de lectura permitido.');
                }
                $trozo = $flujo->read(min(65536, LectorJsonLocal::MAX_BYTES + 1 - strlen($crudo)));
                if ($trozo === '' && ! $flujo->eof()) {
                    throw new SeriePrecipitacionInvalida('PROVEEDOR_NO_DISPONIBLE', 'La lectura del proveedor no pudo completarse.');
                }
                $crudo .= $trozo;
            }
            if (strlen($crudo) > LectorJsonLocal::MAX_BYTES) {
                throw new SeriePrecipitacionInvalida('RESPUESTA_DEMASIADO_GRANDE', 'La captura supera 8 MiB; no se guardó una versión parcial.');
            }
            $datos = json_decode($crudo, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($datos) || array_is_list($datos) || isset($datos['error'])) {
                throw new SeriePrecipitacionInvalida('RESPUESTA_PROVEEDOR_INVALIDA', 'El proveedor no devolvió una serie diaria válida.');
            }
            $celda = [$datos['longitude'] ?? null, $datos['latitude'] ?? null];
            foreach ([180, 90] as $i => $limite) {
                if ((! is_float($celda[$i]) && ! is_int($celda[$i])) || ! is_finite((float) $celda[$i]) || abs($celda[$i]) > $limite) {
                    throw new SeriePrecipitacionInvalida('RESPUESTA_PROVEEDOR_INVALIDA', 'El proveedor debe declarar coordenadas de celda WGS84 válidas.');
                }
            }
            // La zona/unidad/fechas/valores se verifican antes de publicar la captura.
            $this->validador->validar($datos, $solicitud['periodo']['desde'], $solicitud['periodo']['hastaIncluido']);
            $recuperado = now()->utc()->format('Y-m-d\TH:i:s.u\Z');
            $procedencia = ['version' => 'procedencia-precipitacion-1.0', 'tipoEvidencia' => 'captura_externa',
                'proveedor' => 'Open-Meteo', 'producto' => 'ERA5', 'naturaleza' => 'reanalisis',
                'muestra' => $solicitud['muestra'], 'coordenadaSolicitada' => $solicitud['coordenadaSolicitada'],
                'coordenadaCelda' => $celda, 'periodo' => $solicitud['periodo'], 'obtenidoEn' => $recuperado,
                'urlOrigen' => $solicitud['url'], 'licencia' => 'https://creativecommons.org/licenses/by/4.0/',
                'atribucion' => 'Open-Meteo.com; ECMWF / Copernicus Climate Change Service, ERA5.', 'sha256' => hash('sha256', $crudo)];

            return ['crudo' => $crudo, 'procedencia' => $procedencia, 'http' => ['estado' => 200,
                'fechaCabecera' => $respuesta->header('Date'), 'tipoContenido' => $respuesta->header('Content-Type')]];
        } catch (SeriePrecipitacionInvalida $e) {
            throw $e;
        } catch (\JsonException) {
            throw new SeriePrecipitacionInvalida('RESPUESTA_PROVEEDOR_INVALIDA', 'La respuesta no contiene JSON utilizable.');
        } catch (Throwable) {
            // No propagar cuerpo, excepción ni trazas del proveedor.
            throw new SeriePrecipitacionInvalida('PROVEEDOR_NO_DISPONIBLE', 'No se pudo obtener la captura de Open-Meteo. Reintenta más tarde.');
        } finally {
            if ($flujo !== null) {
                $flujo->close();
            }
        }
    }

    private function coordenada(mixed $v, int $limite): float
    {
        if ((! is_float($v) && ! is_int($v) && ! is_string($v))
            || ! preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]{1,6})?$/D', (string) $v)
            || ! is_finite((float) $v) || abs((float) $v) > $limite) {
            throw new SeriePrecipitacionInvalida('COORDENADA_INVALIDA', 'Indica coordenadas decimales WGS84 con punto, máximo seis decimales y sin listas.');
        }

        return (float) $v === 0.0 ? 0.0 : (float) $v;
    }

    private function decimal(float $v): string
    {
        return rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
    }
}
