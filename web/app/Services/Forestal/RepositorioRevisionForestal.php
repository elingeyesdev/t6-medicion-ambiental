<?php

declare(strict_types=1);

namespace App\Services\Forestal;

use App\Services\Api\ErrorConsulta;
use JsonException;
use stdClass;

/** Lectura de paquetes privados de revisión; no acredita aprobación científica. */
final class RepositorioRevisionForestal
{
    private const MAX_MANIFIESTO = 65536;

    private const MAX_RESUMEN = 2097152;

    private const MAX_CAPA = 33554432;

    private const MAX_CELDAS = 100000;

    private const EPS_COORDENADA = 1e-10;

    public function __construct(private readonly string $directorio) {}

    public function consultar(string $id, int $desde, int $hasta): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $id) || $desde < 1000 || $hasta > 9999 || $desde > $hasta) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Identificador o intervalo de revisión inválido.');
        }
        $base = $this->directorioSeguro($this->directorio);
        $carpeta = $this->directorioSeguro($base.DIRECTORY_SEPARATOR.$id);
        $manifiestoBytes = $this->leer($carpeta, 'manifiesto.json', self::MAX_MANIFIESTO);
        if (! hash_equals($id, hash('sha256', $manifiestoBytes))) {
            $this->invalido();
        }
        $m = $this->objeto($manifiestoBytes);
        if (($m->formato ?? null) !== 'revision-forestal-1' || ($m->estado ?? null) !== 'revision_tecnica'
            || ! ($m->identidad ?? null) instanceof stdClass || ! ($m->archivos ?? null) instanceof stdClass) {
            $this->invalido();
        }
        $this->claves($m, ['formato', 'estado', 'identidad', 'archivos']);
        $this->claves($m->archivos, ['resumen.json', 'perdida.geojson']);
        $bytes = [];
        // Siempre se leen y verifican ambos archivos, incluso para un único año sin pérdida.
        foreach (['resumen.json' => self::MAX_RESUMEN, 'perdida.geojson' => self::MAX_CAPA] as $nombre => $limite) {
            $hash = $m->archivos->{$nombre};
            if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                $this->invalido();
            }
            $bytes[$nombre] = $this->leer($carpeta, $nombre, $limite);
            if (! hash_equals($hash, hash('sha256', $bytes[$nombre]))) {
                $this->invalido();
            }
        }
        $r = $this->objeto($bytes['resumen.json']);
        $c = $this->objeto($bytes['perdida.geojson']);
        $filas = $this->validarResumen($r);
        $this->claves($m->identidad, ['fuente_version', 'tesela', 'captura_sha256', 'criterio', 'ventana_pixeles', 'consulta']);
        $esperada = (object) [
            'fuente_version' => $r->fuente->version, 'tesela' => $r->fuente->tesela,
            'captura_sha256' => $r->fuente->captura_sha256, 'criterio' => $r->criterio,
            'ventana_pixeles' => $r->ventana_pixeles, 'consulta' => $r->consulta,
        ];
        if (! $this->iguales($m->identidad, $esperada)) {
            $this->invalido();
        }
        $this->validarCapa($c, $r, $filas);
        if ($desde < $r->consulta->desde || $hasta > $r->consulta->hasta) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El intervalo excede la consulta del paquete de revisión.');
        }
        for ($anio = $desde; $anio <= $hasta; $anio++) {
            if (! isset($filas[$anio])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'El intervalo contiene un año no disponible.');
            }
        }
        $total = '0.00000000';
        $serie = [];
        foreach ($r->serie as $fila) {
            if ($fila->anio >= $desde && $fila->anio <= $hasta) {
                $serie[] = $fila;
                $total = $this->sumar($total, $this->decimal($fila->perdida_ha));
            }
        }
        $r->consulta->desde = $desde;
        $r->consulta->hasta = $hasta;
        $r->serie = $serie;
        $r->total_periodo_ha = $total;
        $c->features = array_values(array_filter($c->features,
            fn (stdClass $f): bool => $f->properties->anio >= $desde && $f->properties->anio <= $hasta));
        $r->conteos_pixeles->perdida_periodo = count($c->features);

        return ['id' => $id, 'revision' => true, 'resumen' => $this->arreglo($r), 'capa' => $this->arreglo($c)];
    }

    private function validarResumen(stdClass $r): array
    {
        if (($r->estado ?? null) !== 'recorte_tecnico_no_validado' || ($r->integrado_en_api ?? null) !== false) {
            $this->invalido();
        }
        foreach (['fuente', 'criterio', 'consulta', 'ventana_pixeles', 'conteos_pixeles'] as $campo) {
            if (! ($r->{$campo} ?? null) instanceof stdClass) {
                $this->invalido();
            }
        }
        foreach (['version', 'tesela', 'captura_sha256'] as $campo) {
            if (! is_string($r->fuente->{$campo} ?? null) || trim($r->fuente->{$campo}) === '') {
                $this->invalido();
            }
        }
        if ($r->fuente->version !== 'GFC-2025-v1.13' || $r->fuente->tesela !== '10S_070W'
            || ! preg_match('/^[a-f0-9]{64}$/D', $r->fuente->captura_sha256)
            || ($r->criterio->adoptado ?? null) !== false || ($r->criterio->base ?? null) !== 2000
            || ($r->criterio->operador ?? null) !== '>=' || ! is_int($r->criterio->porcentaje ?? null)
            || $r->criterio->porcentaje < 1 || $r->criterio->porcentaje > 100
            || ! is_int($r->consulta->desde ?? null) || ! is_int($r->consulta->hasta ?? null)
            || $r->consulta->desde < 2001 || $r->consulta->hasta > 2025 || $r->consulta->desde > $r->consulta->hasta
            || ($r->consulta->extremos_incluidos ?? null) !== true
            || ! property_exists($r, 'cobertura_observaciones_fraccion')
            || ($r->cobertura_observaciones_fraccion !== null && (! $this->numero($r->cobertura_observaciones_fraccion)
                || $r->cobertura_observaciones_fraccion < 0 || $r->cobertura_observaciones_fraccion > 1))
            || ! is_array($r->limites ?? null) || ! array_is_list($r->limites) || $r->limites === []) {
            $this->invalido();
        }
        foreach ($r->limites as $limite) {
            if (! is_string($limite) || trim($limite) === '') {
                $this->invalido();
            }
        }
        $v = $r->ventana_pixeles;
        foreach (['columna', 'fila', 'ancho', 'alto'] as $campo) {
            if (! is_int($v->{$campo} ?? null) || $v->{$campo} < 0 || $v->{$campo} > 1000000) {
                $this->invalido();
            }
        }
        if ($v->ancho < 1 || $v->alto < 1 || $v->ancho * $v->alto > self::MAX_CELDAS) {
            $this->invalido();
        }
        $g = $r->fuente->cuadricula ?? null;
        if (! $g instanceof stdClass || ($g->epsg ?? null) !== 4326 || ($g->pixel ?? null) !== 'area'
            || ! $this->numero($g->oeste ?? null) || ! $this->numero($g->norte ?? null)
            || ! $this->numero($g->paso_grados ?? null) || $g->paso_grados !== 0.00025
            || $g->oeste != -70 || $g->norte != -10
            || ! is_int($g->ancho ?? null) || ! is_int($g->alto ?? null)
            || $g->ancho !== 40000 || $g->alto !== 40000
            || $v->columna + $v->ancho > $g->ancho || $v->fila + $v->alto > $g->alto) {
            $this->invalido();
        }
        $bbox = [$g->oeste + $v->columna * $g->paso_grados,
            $g->norte - ($v->fila + $v->alto) * $g->paso_grados,
            $g->oeste + ($v->columna + $v->ancho) * $g->paso_grados,
            $g->norte - $v->fila * $g->paso_grados];
        if (! is_array($r->bbox ?? null) || ! array_is_list($r->bbox) || count($r->bbox) !== 4
            || $bbox[0] < -180 || $bbox[2] > 180 || $bbox[1] < -90 || $bbox[3] > 90) {
            $this->invalido();
        }
        foreach ($bbox as $i => $coordenada) {
            $this->cerca($r->bbox[$i], $coordenada);
        }
        $p = $r->conteos_pixeles;
        foreach (['sin_dato_base', 'agua_base', 'tierra_base', 'arbolado_base', 'perdida_periodo'] as $campo) {
            if (! is_int($p->{$campo} ?? null) || $p->{$campo} < 0 || $p->{$campo} > $v->ancho * $v->alto) {
                $this->invalido();
            }
        }
        if ($p->sin_dato_base + $p->agua_base + $p->tierra_base !== $v->ancho * $v->alto
            || $p->arbolado_base > $p->tierra_base || $p->perdida_periodo > $p->arbolado_base
            || ! is_array($r->serie ?? null) || ! array_is_list($r->serie) || count($r->serie) !== 25) {
            $this->invalido();
        }
        $filas = [];
        $total = '0.00000000';
        $cantidad = 0;
        foreach ($r->serie as $fila) {
            if (! $fila instanceof stdClass || ! is_int($fila->anio ?? null) || $fila->anio < 2001 || $fila->anio > 2025
                || isset($filas[$fila->anio])) {
                $this->invalido();
            }
            $area = $this->decimal($fila->perdida_ha ?? null);
            $filas[$fila->anio] = $fila;
            if ($fila->anio >= $r->consulta->desde && $fila->anio <= $r->consulta->hasta) {
                $total = $this->sumar($total, $area);
                $cantidad++;
            }
        }
        // Un año ausente nunca se convierte en cero ni se considera disponible.
        for ($anio = $r->consulta->desde; $anio <= $r->consulta->hasta; $anio++) {
            if (! isset($filas[$anio])) {
                $this->invalido();
            }
        }
        $original = $this->decimal($r->total_periodo_ha ?? null);
        // El generador redondea el total geodésico antes de redondear cada año.
        if (abs((float) $original - (float) $total) > ($cantidad + 1) * 0.000000005 + 1e-9) {
            $this->invalido();
        }

        return $filas;
    }

    private function validarCapa(stdClass $c, stdClass $r, array $filas): void
    {
        if (($c->type ?? null) !== 'FeatureCollection' || ! is_array($c->features ?? null)
            || ! array_is_list($c->features) || count($c->features) > self::MAX_CELDAS
            || count($c->features) !== $r->conteos_pixeles->perdida_periodo) {
            $this->invalido();
        }
        // Evita conservar extensiones/capacidades ajenas al GeoJSON de celdas.
        $this->claves($c, ['type', 'features']);
        $vistos = $sumas = $conteos = [];
        $v = $r->ventana_pixeles;
        $g = $r->fuente->cuadricula;
        foreach ($c->features as $f) {
            if (! $f instanceof stdClass || ($f->type ?? null) !== 'Feature' || ! is_string($f->id ?? null)
                || ! preg_match('/^(0|[1-9][0-9]{0,6})-(0|[1-9][0-9]{0,6})$/D', $f->id, $partes)
                || isset($vistos[$f->id]) || ! ($f->properties ?? null) instanceof stdClass
                || ! ($f->geometry ?? null) instanceof stdClass) {
                $this->invalido();
            }
            $this->claves($f, ['type', 'id', 'properties', 'geometry']);
            $this->claves($f->properties, ['anio', 'area_ha']);
            $this->claves($f->geometry, ['type', 'coordinates']);
            $vistos[$f->id] = true;
            $fila = (int) $partes[1];
            $columna = (int) $partes[2];
            $anio = $f->properties->anio;
            if ($fila < $v->fila || $fila >= $v->fila + $v->alto || $columna < $v->columna || $columna >= $v->columna + $v->ancho
                || ! is_int($anio) || ! isset($filas[$anio]) || $anio < $r->consulta->desde || $anio > $r->consulta->hasta) {
                $this->invalido();
            }
            $area = $this->decimal($f->properties->area_ha);
            if ($f->properties->area_ha <= 0) {
                $this->invalido();
            }
            $sumas[$anio] = $this->sumar($sumas[$anio] ?? '0.00000000', $area);
            $conteos[$anio] = ($conteos[$anio] ?? 0) + 1;
            $oeste = $g->oeste + $columna * $g->paso_grados;
            $norte = $g->norte - $fila * $g->paso_grados;
            $este = $oeste + $g->paso_grados;
            $sur = $norte - $g->paso_grados;
            $esperado = [[$oeste, $norte], [$oeste, $sur], [$este, $sur], [$este, $norte], [$oeste, $norte]];
            $coords = $f->geometry->coordinates ?? null;
            if (($f->geometry->type ?? null) !== 'Polygon' || ! is_array($coords) || ! array_is_list($coords)
                || count($coords) !== 1 || ! is_array($coords[0]) || ! array_is_list($coords[0]) || count($coords[0]) !== 5) {
                $this->invalido();
            }
            foreach ($coords[0] as $i => $punto) {
                if (! is_array($punto) || ! array_is_list($punto) || count($punto) !== 2) {
                    $this->invalido();
                }
                foreach ($punto as $j => $valor) {
                    $this->cerca($valor, $esperado[$i][$j]);
                    if ($valor < $r->bbox[$j] - self::EPS_COORDENADA || $valor > $r->bbox[$j + 2] + self::EPS_COORDENADA) {
                        $this->invalido();
                    }
                }
            }
        }
        foreach ($filas as $anio => $fila) {
            if ($anio >= $r->consulta->desde && $anio <= $r->consulta->hasta
                && abs((float) ($sumas[$anio] ?? '0.00000000') - $fila->perdida_ha) > (($conteos[$anio] ?? 0) + 1) * 0.000000005 + 1e-9) {
                $this->invalido();
            }
        }
    }

    private function directorioSeguro(string $ruta): string
    {
        if ($ruta === '' || str_contains($ruta, "\0") || str_contains($ruta, '://')) {
            $this->invalido();
        }
        // Revisar también ancestros, antes de que realpath oculte enlaces/junctions.
        $actual = $ruta;
        do {
            clearstatcache(true, $actual);
            $estado = @lstat($actual);
            // PHP en Windows puede informar junctions con mode=0 e is_link=false.
            if ($estado === false || ($estado['mode'] & 0170000) !== 0040000 || is_link($actual)) {
                $this->invalido();
            }
            $padre = dirname($actual);
            if ($padre === $actual) {
                break;
            }
            $actual = $padre;
        } while ($actual !== '.');
        $real = realpath($ruta);
        if ($real === false || ! is_dir($real)) {
            $this->invalido();
        }

        return $real;
    }

    private function leer(string $carpeta, string $nombre, int $limite): string
    {
        $ruta = $carpeta.DIRECTORY_SEPARATOR.$nombre;
        clearstatcache(true, $ruta);
        $antes = @lstat($ruta);
        if ($antes === false || ($antes['mode'] & 0170000) !== 0100000 || is_link($ruta)
            || $antes['size'] < 1 || $antes['size'] > $limite || realpath($ruta) !== $ruta) {
            $this->invalido();
        }
        $archivo = @fopen($ruta, 'rb');
        if ($archivo === false) {
            $this->invalido();
        }
        try {
            $abierto = fstat($archivo);
            $bytes = stream_get_contents($archivo, $limite + 1);
            $final = fstat($archivo);
            clearstatcache(true, $ruta);
            $despues = @lstat($ruta);
            if ($abierto === false || $final === false || $despues === false || $bytes === false
                || strlen($bytes) !== $antes['size'] || strlen($bytes) > $limite || is_link($ruta)
                || realpath($ruta) !== $ruta) {
                $this->invalido();
            }
            foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $campo) {
                if ($antes[$campo] !== $abierto[$campo] || $antes[$campo] !== $final[$campo] || $antes[$campo] !== $despues[$campo]) {
                    $this->invalido();
                }
            }
        } finally {
            fclose($archivo);
        }
        $this->directorioSeguro($carpeta);

        return $bytes;
    }

    private function objeto(string $bytes): stdClass
    {
        try {
            $objeto = json_decode($bytes, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalido();
        }
        if (! $objeto instanceof stdClass) {
            $this->invalido();
        }

        $this->sinClavesDuplicadas($bytes);

        return $objeto;
    }

    /** json_decode acepta claves repetidas; se rechazan también las equivalentes escapadas. */
    private function sinClavesDuplicadas(string $bytes): void
    {
        $pila = [];
        $n = strlen($bytes);
        for ($i = 0; $i < $n; $i++) {
            $i += strcspn($bytes, '"{}[]', $i);
            if ($i >= $n) {
                break;
            }
            $caracter = $bytes[$i];
            if ($caracter === '{' || $caracter === '[') {
                $pila[] = $caracter === '{' ? [] : null;
            } elseif ($caracter === '}' || $caracter === ']') {
                array_pop($pila);
            } else {
                $inicio = $i++;
                while ($bytes[$i] !== '"') {
                    $i += $bytes[$i] === '\\' ? 2 : 1;
                }
                $siguiente = $i + 1 + strspn($bytes, " \n\r\t", $i + 1);
                if ($siguiente < $n && $bytes[$siguiente] === ':') {
                    $clave = json_decode(substr($bytes, $inicio, $i - $inicio + 1), flags: JSON_THROW_ON_ERROR);
                    $nivel = count($pila) - 1;
                    if (isset($pila[$nivel][$clave])) {
                        $this->invalido();
                    }
                    $pila[$nivel][$clave] = true;
                }
            }
        }
    }

    private function claves(stdClass $objeto, array $esperadas): void
    {
        $claves = array_keys(get_object_vars($objeto));
        sort($claves);
        sort($esperadas);
        if ($claves !== $esperadas) {
            $this->invalido();
        }
    }

    private function iguales(mixed $a, mixed $b): bool
    {
        if ($a instanceof stdClass && $b instanceof stdClass) {
            $a = get_object_vars($a);
            $b = get_object_vars($b);
            ksort($a);
            ksort($b);
        }
        if (is_array($a) && is_array($b)) {
            if (array_keys($a) !== array_keys($b)) {
                return false;
            }
            foreach ($a as $k => $valor) {
                if (! $this->iguales($valor, $b[$k])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }

    private function numero(mixed $n): bool
    {
        return (is_int($n) || is_float($n)) && is_finite((float) $n);
    }

    private function cerca(mixed $n, float $esperado): void
    {
        if (! $this->numero($n) || abs($n - $esperado) > self::EPS_COORDENADA) {
            $this->invalido();
        }
    }

    private function decimal(mixed $n): string
    {
        if (! $this->numero($n) || $n < 0 || $n > 1000000000) {
            $this->invalido();
        }
        $cadena = number_format($n, 8, '.', '');
        if ((float) $n !== (float) $cadena) {
            $this->invalido();
        }

        return $cadena;
    }

    /** Suma de cadenas a escala fija; nunca acumula las áreas como float. */
    private function sumar(string $a, string $b): string
    {
        $a = strrev(str_replace('.', '', $a));
        $b = strrev(str_replace('.', '', $b));
        $suma = '';
        $acarreo = 0;
        for ($i = 0, $n = max(strlen($a), strlen($b)); $i < $n; $i++) {
            $digito = (int) ($a[$i] ?? '0') + (int) ($b[$i] ?? '0') + $acarreo;
            $suma .= (string) ($digito % 10);
            $acarreo = intdiv($digito, 10);
        }
        if ($acarreo !== 0) {
            $suma .= (string) $acarreo;
        }
        $suma = str_pad(strrev($suma), 9, '0', STR_PAD_LEFT);

        return substr($suma, 0, -8).'.'.substr($suma, -8);
    }

    private function arreglo(mixed $valor): mixed
    {
        if (is_float($valor) && ! is_finite($valor)) {
            $this->invalido();
        }
        if ($valor instanceof stdClass) {
            // Conservar {} de metadatos en lugar de transformarlo silenciosamente en [].
            if (get_object_vars($valor) === []) {
                return $valor;
            }
            $valor = get_object_vars($valor);
        }

        return is_array($valor) ? array_map($this->arreglo(...), $valor) : $valor;
    }

    private function invalido(): never
    {
        throw new ErrorConsulta('PRODUCTO_INVALIDO', 'El paquete de revisión forestal no es válido.', 503);
    }
}
