<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use DateTimeImmutable;
use DateTimeZone;

/** Normaliza declaraciones de procedencia; no certifica su autenticidad externa. */
final class ProcedenciaSeriePrecipitacion
{
    public function normalizar(array $p): array
    {
        $campos = ['version', 'tipoEvidencia', 'proveedor', 'producto', 'naturaleza', 'muestra',
            'coordenadaSolicitada', 'coordenadaCelda', 'periodo', 'obtenidoEn', 'urlOrigen', 'licencia', 'atribucion', 'sha256'];
        if (array_diff(array_keys($p), $campos) || array_diff($campos, array_keys($p))) {
            $this->invalida('La procedencia debe incluir exactamente los campos documentados.');
        }
        if ($p['version'] !== 'procedencia-precipitacion-1.0'
            || ! in_array($p['tipoEvidencia'], ['controlada', 'captura_externa'], true)
            || ! in_array($p['naturaleza'], ['observacion', 'reanalisis', 'simulacion'], true)) {
            $this->invalida('Versión, tipo de evidencia o naturaleza no admitidos.');
        }
        if (($p['tipoEvidencia'] === 'controlada') !== ($p['naturaleza'] === 'simulacion')) {
            $this->invalida('Una fixture controlada debe identificarse como simulación.');
        }
        if (! is_string($p['muestra']) || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D', $p['muestra'])
            || ! is_string($p['sha256']) || ! preg_match('/^[a-f0-9]{64}$/D', $p['sha256'])) {
            $this->invalida('Muestra o SHA-256 inválidos.');
        }
        $periodo = $p['periodo'];
        if (! is_array($periodo) || count($periodo) !== 3 || ($periodo['zona'] ?? null) !== 'UTC'
            || ! is_string($periodo['desde'] ?? null) || ! is_string($periodo['hastaIncluido'] ?? null)) {
            $this->invalida('El período debe declarar desde, hastaIncluido y zona UTC.');
        }
        $url = $p['urlOrigen'];
        if ($p['tipoEvidencia'] === 'controlada') {
            if ($url !== null) {
                $this->invalida('Una fixture controlada no debe presentarse con URL de un proveedor.');
            }
        } else {
            $partes = is_string($url) ? parse_url($url) : false;
            if (! is_string($url) || strlen($url) > 4096 || $partes === false || ($partes['scheme'] ?? null) !== 'https'
                || empty($partes['host']) || isset($partes['user']) || isset($partes['pass']) || isset($partes['fragment'])) {
                $this->invalida('La URL declarada debe ser HTTPS, sin credenciales ni fragmento. No se consulta.');
            }
        }

        return ['version' => $p['version'], 'tipoEvidencia' => $p['tipoEvidencia'],
            'proveedor' => $this->texto($p['proveedor'], 120), 'producto' => $this->texto($p['producto'], 120),
            'naturaleza' => $p['naturaleza'], 'muestra' => $p['muestra'],
            'coordenadaSolicitada' => $this->coordenada($p['coordenadaSolicitada']),
            'coordenadaCelda' => $this->coordenada($p['coordenadaCelda']),
            'periodo' => ['desde' => $periodo['desde'], 'hastaIncluido' => $periodo['hastaIncluido'], 'zona' => 'UTC'],
            'obtenidoEn' => $this->instante($p['obtenidoEn']), 'urlOrigen' => $url,
            'licencia' => $this->texto($p['licencia'], 500), 'atribucion' => $this->texto($p['atribucion'], 1000),
            'sha256' => $p['sha256']];
    }

    private function texto(mixed $valor, int $maximo): string
    {
        if (! is_string($valor) || trim($valor) === '' || strlen($valor) > $maximo || preg_match('/[\x00-\x1f]/', $valor)) {
            $this->invalida('Los textos de procedencia deben ser breves, no vacíos y sin caracteres de control.');
        }

        return trim($valor);
    }

    private function coordenada(mixed $valor): array
    {
        if (! is_array($valor) || ! array_is_list($valor) || count($valor) !== 2) {
            $this->invalida('Las coordenadas deben ser [longitud, latitud].');
        }
        foreach ([180, 90] as $i => $limite) {
            if ((! is_int($valor[$i]) && ! is_float($valor[$i])) || ! is_finite((float) $valor[$i]) || abs($valor[$i]) > $limite) {
                $this->invalida('Coordenada fuera del rango WGS84.');
            }
        }

        return [(float) $valor[0], (float) $valor[1]];
    }

    public function instante(mixed $valor): string
    {
        if (! is_string($valor) || ! preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})(?:\.([0-9]{1,6}))?Z$/D', $valor, $m)) {
            $this->invalida('El instante debe declarar UTC con segundos y sufijo Z.');
        }
        $normalizado = $m[1].'.'.str_pad($m[2] ?? '', 6, '0').'Z';
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $normalizado, new DateTimeZone('UTC'));
        if ($fecha === false || $fecha->format('Y-m-d\TH:i:s.u\Z') !== $normalizado || substr($normalizado, 0, 4) === '0000') {
            $this->invalida('El instante de procedencia es imposible.');
        }

        return $normalizado;
    }

    private function invalida(string $mensaje): never
    {
        throw new SeriePrecipitacionInvalida('PROCEDENCIA_INVALIDA', $mensaje);
    }
}
