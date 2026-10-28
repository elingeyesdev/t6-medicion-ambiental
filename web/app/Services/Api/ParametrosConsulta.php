<?php

namespace App\Services\Api;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

final class ParametrosConsulta
{
    public static function leer(Request $request, array $permitidos): array
    {
        // PHP colapsa las claves repetidas: comprobar la URL original antes de usar query().
        $vistos = [];
        foreach (explode('&', $request->server('QUERY_STRING', '')) as $par) {
            if ($par === '') {
                continue;
            }
            $nombre = urldecode(explode('=', $par, 2)[0]);
            if (isset($vistos[$nombre]) || ! in_array($nombre, $permitidos, true)) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Parámetro desconocido o repetido.');
            }
            $vistos[$nombre] = true;
        }
        $valores = $request->query();
        foreach ($valores as $nombre => $valor) {
            if (! in_array($nombre, $permitidos, true) || ! is_string($valor) || trim($valor) === '') {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Cada parámetro debe contener un único valor no vacío.');
            }
        }

        return $valores;
    }

    public static function entero(array $p, string $nombre, int $defecto, int $maximo): int
    {
        if (! isset($p[$nombre])) {
            return $defecto;
        }
        if (! preg_match('/^[1-9][0-9]{0,8}$/D', $p[$nombre]) || (int) $p[$nombre] > $maximo) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', "Valor inválido de $nombre.");
        }

        return (int) $p[$nombre];
    }

    public static function instante(?string $texto): DateTimeImmutable
    {
        if ($texto === null || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $texto)
            || (preg_match('/[+-]14:(?!00)/', $texto))) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'desde y hasta requieren fecha ISO 8601, segundos y zona explícita.');
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $texto));
        $errores = DateTimeImmutable::getLastErrors();
        if ($fecha === false || ($errores !== false && ($errores['warning_count'] || $errores['error_count']))) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'La fecha o la hora no es válida.');
        }

        return $fecha->setTimezone(new DateTimeZone('UTC'));
    }

    public static function utc(DateTimeImmutable $fecha): string
    {
        return $fecha->format('Y-m-d\TH:i:s\Z');
    }
}
