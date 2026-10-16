<?php

namespace App\Services\FocosCalor;

use App\Services\Api\ErrorConsulta;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

final class ClienteFirms
{
    public function disponibilidad(): array
    {
        $filas = $this->csv($this->descargar('data_availability', config('focos.producto')), ['data_id', 'min_date', 'max_date']);
        foreach ($filas as $f) {
            if ($f['data_id'] === config('focos.producto')) {
                foreach (['min_date', 'max_date'] as $columna) {
                    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f[$columna]);
                    if (! $d || $d->format('Y-m-d') !== $f[$columna]) {
                        $this->fallo();
                    }
                }
                if ($f['min_date'] > $f['max_date']) {
                    $this->fallo();
                }

                return ['desde' => $f['min_date'].'T00:00:00Z', 'hasta' => (new DateTimeImmutable($f['max_date'].'T00:00:00Z'))->modify('+1 day')->format('Y-m-d\TH:i:s\Z')];
            }
        }
        $this->fallo();
    }

    public function area(array $caja, DateTimeImmutable $inicio, int $dias): array
    {
        // Redondear hacia afuera solo la consulta al proveedor; el filtro usa el polígono exacto.
        $bbox = implode(',', [floor($caja[0] * 10000) / 10000, floor($caja[1] * 10000) / 10000, ceil($caja[2] * 10000) / 10000, ceil($caja[3] * 10000) / 10000]);
        $sufijo = config('focos.producto').'/'.$bbox.'/'.$dias.'/'.$inicio->format('Y-m-d');

        return $this->csv($this->descargar('area', $sufijo), ['latitude', 'longitude', 'acq_date', 'acq_time']);
    }

    private function descargar(string $operacion, string $sufijo): string
    {
        $clave = config('focos.clave');
        if (! is_string($clave) || ! preg_match('/^[a-zA-Z0-9]{16,128}$/D', $clave)) {
            throw new ErrorConsulta('PROVEEDOR_NO_DISPONIBLE', 'Configura NASA_FIRMS_CLAVE únicamente en el backend.', 503);
        }
        try {
            // Destino fijo y sin redirecciones. No registrar URL: FIRMS lleva la clave en la ruta.
            $r = Http::connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get('https://firms.modaps.eosdis.nasa.gov/api/'.$operacion.'/csv/'.$clave.'/'.$sufijo);
            if (! $r->successful()) {
                $this->fallo();
            }
            $flujo = $r->toPsrResponse()->getBody();
            $texto = '';
            while (! $flujo->eof() && strlen($texto) <= 8 * 1024 * 1024) {
                $texto .= $flujo->read(65536);
            }
            $flujo->close();
            if (strlen($texto) > 8 * 1024 * 1024) {
                $this->fallo();
            }

            return $texto;
        } catch (\Throwable) {
            // Nunca conservar la excepción del cliente HTTP ni su URL con credenciales.
            $this->fallo();
        }
    }

    private function csv(string $texto, array $obligatorias): array
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, ltrim($texto, "\xEF\xBB\xBF"));
        rewind($f);
        $cabecera = fgetcsv($f, 0, ',', '"', '');
        if (! is_array($cabecera) || array_diff($obligatorias, $cabecera) !== [] || count(array_unique($cabecera)) !== count($cabecera)) {
            fclose($f);
            $this->fallo();
        }
        $filas = [];
        while (($fila = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($fila === [null]) {
                continue;
            }
            if (count($fila) !== count($cabecera) || count($filas) >= config('focos.maximo_filas')) {
                fclose($f);
                $this->fallo();
            }
            $filas[] = array_combine($cabecera, $fila);
        }
        fclose($f);

        return $filas;
    }

    private function fallo(): never
    {
        throw new ErrorConsulta('PROVEEDOR_NO_DISPONIBLE', 'NASA FIRMS no devolvió una respuesta utilizable. Reintenta más tarde.', 503);
    }
}
