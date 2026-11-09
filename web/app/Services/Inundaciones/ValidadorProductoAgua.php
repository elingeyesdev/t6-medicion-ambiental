<?php

declare(strict_types=1);

namespace App\Services\Inundaciones;

use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\FiltroTerritorialInvalido;
use DateTimeImmutable;
use InvalidArgumentException;

/** Estructura del contrato local y coherencia del resultado; no certifica su exactitud ambiental. */
final class ValidadorProductoAgua
{
    public function __construct(private readonly CatalogoTerritorial $territorios) {}

    public function validar(array|\stdClass $dato): array
    {
        $schema = json_decode(file_get_contents(base_path('../compartido/api/inundaciones/respuesta.schema.json')), true, 64, JSON_THROW_ON_ERROR);
        $this->estructura($dato, $schema);
        $dato = json_decode(json_encode($dato, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 64, JSON_THROW_ON_ERROR);
        $this->exigir(preg_match('/^BO07(?:[0-9]{2}){0,2}$/D', $dato['territorio']['identificador']) === 1);
        try {
            $this->territorios->buscar($dato['territorio']['identificador']);
        } catch (FiltroTerritorialInvalido) {
            $this->exigir(false);
        }
        $this->exigir($dato['tipoEvidencia'] === 'resultado_validado');
        $a = $dato['comparacion']['referencia'];
        $b = $dato['comparacion']['comparacion'];
        $this->exigir($a < $b && $b <= now('UTC')->toDateString());
        $c = $dato['cobertura'];
        $r = $dato['resumen'];
        $this->exigir($c['areaEstudiadaKm2'] > 0 && $c['areaComparableKm2'] <= $c['areaEstudiadaKm2']);
        $this->exigir(abs($c['fraccionComparable'] - $c['areaComparableKm2'] / $c['areaEstudiadaKm2']) <= 1e-8);
        $valores = [$r['aguaPersistenteKm2'], $r['aguaNuevaKm2'], $r['aguaRetiradaKm2']];
        if ($dato['estado'] === 'sin_datos') {
            $this->exigir($valores === [null, null, null] && $c['areaComparableKm2'] == 0);
        } else {
            $this->exigir(! in_array(null, $valores, true) && $c['areaComparableKm2'] > 0);
            $this->exigir(array_sum($valores) <= $c['areaComparableKm2'] + 1e-8);
            $this->exigir(($dato['estado'] === 'sin_cambios') === ($valores[1] == 0 && $valores[2] == 0));
        }
        $ids = [];
        foreach ($dato['capa']['features'] as $feature) {
            $this->exigir(! isset($ids[$feature['id']]));
            $ids[$feature['id']] = true;
            foreach ($feature['geometry']['coordinates'] as $anillo) {
                $this->exigir($anillo[0] == $anillo[array_key_last($anillo)]);
            }
            $clase = $feature['properties']['clase'];
            $this->exigir($dato['estado'] !== 'sin_datos' || $clase === 'no_comparable');
            $this->exigir($dato['estado'] !== 'sin_cambios' || ! in_array($clase, ['agua_nueva', 'agua_retirada'], true));
        }

        return $dato;
    }

    /** Solo los términos usados por este contrato; una ampliación desconocida falla. */
    private function estructura(mixed $valor, array $schema): void
    {
        $permitidos = ['$schema', 'type', 'const', 'enum', 'additionalProperties', 'required',
            'properties', 'minLength', 'maxLength', 'format', 'minimum', 'maximum',
            'exclusiveMinimum', 'items', 'minItems', 'maxItems', 'prefixItems'];
        $this->exigir(array_diff(array_keys($schema), $permitidos) === []);
        if (array_key_exists('const', $schema)) {
            $this->exigir($valor === $schema['const']);
        }
        if (isset($schema['enum'])) {
            $this->exigir(in_array($valor, $schema['enum'], true));
        }
        if (isset($schema['type'])) {
            $tipos = (array) $schema['type'];
            $coincide = false;
            foreach ($tipos as $tipo) {
                $coincide = $coincide || match ($tipo) {
                    'object' => $valor instanceof \stdClass || (is_array($valor) && ! array_is_list($valor)),
                    'array' => is_array($valor) && array_is_list($valor),
                    'string' => is_string($valor),
                    'number' => (is_int($valor) || is_float($valor)) && is_finite((float) $valor),
                    'null' => $valor === null,
                    default => false,
                };
            }
            $this->exigir($coincide);
        }
        if (is_string($valor)) {
            $this->exigir(mb_strlen($valor) >= ($schema['minLength'] ?? 0));
            $this->exigir(mb_strlen($valor) <= ($schema['maxLength'] ?? PHP_INT_MAX));
            if (isset($schema['format'])) {
                $this->exigir(preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor) === 1);
                $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
                $this->exigir($schema['format'] === 'date' && $fecha !== false && $fecha->format('Y-m-d') === $valor);
            }
        }
        if (is_int($valor) || is_float($valor)) {
            $this->exigir(is_finite((float) $valor));
            $this->exigir(! isset($schema['minimum']) || $valor >= $schema['minimum']);
            $this->exigir(! isset($schema['maximum']) || $valor <= $schema['maximum']);
            $this->exigir(! isset($schema['exclusiveMinimum']) || $valor > $schema['exclusiveMinimum']);
        }
        if (($schema['type'] ?? null) === 'object') {
            $valor = $valor instanceof \stdClass ? get_object_vars($valor) : $valor;
            $propiedades = $schema['properties'] ?? [];
            $this->exigir(array_diff($schema['required'] ?? [], array_keys($valor)) === []);
            if (($schema['additionalProperties'] ?? true) === false) {
                $this->exigir(array_diff(array_keys($valor), array_keys($propiedades)) === []);
            }
            foreach ($valor as $clave => $item) {
                if (isset($propiedades[$clave])) {
                    $this->estructura($item, $propiedades[$clave]);
                }
            }
        }
        if (($schema['type'] ?? null) === 'array') {
            $this->exigir(count($valor) >= ($schema['minItems'] ?? 0) && count($valor) <= ($schema['maxItems'] ?? PHP_INT_MAX));
            foreach ($valor as $i => $item) {
                if (isset($schema['prefixItems'][$i])) {
                    $this->estructura($item, $schema['prefixItems'][$i]);
                } else {
                    $this->exigir(($schema['items'] ?? true) !== false);
                    if (is_array($schema['items'] ?? null)) {
                        $this->estructura($item, $schema['items']);
                    }
                }
            }
        }
    }

    private function exigir(bool $condicion): void
    {
        if (! $condicion) {
            throw new InvalidArgumentException('El producto no coincide con el contrato de agua.');
        }
    }
}
