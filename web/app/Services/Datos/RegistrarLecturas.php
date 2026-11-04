<?php

namespace App\Services\Datos;

use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Services\Alertas\EvaluarAlertas;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PDOException;

final class RegistrarLecturas
{
    public function __construct(private CatalogoAmbiental $catalogo) {}

    public function ejecutar(array $filas, ?int $actor = null, bool $comprobar = false): array
    {
        Validator::make(['lecturas' => $filas], ['lecturas' => ['required', 'array', 'list', 'min:1', 'max:1000']])->validate();
        $metricas = $this->catalogo->metricas();
        $normalizadas = [];
        foreach ($filas as $i => $fila) {
            $metrica = is_array($fila) && is_string($fila['metrica'] ?? null) ? $fila['metrica'] : '';
            $limites = $metricas[$metrica] ?? ['minimo' => 0, 'maximo' => 0];
            Validator::make(['lecturas' => [$i => $fila]], [
                "lecturas.$i" => ['required', 'array:punto,metrica,valor,instante,origen,fuente'],
                "lecturas.$i.punto" => ['required', 'string', 'max:60', 'exists:puntos_ambientales,codigo'],
                "lecturas.$i.metrica" => ['required', Rule::in(array_keys($metricas))],
                "lecturas.$i.valor" => ['required', 'numeric', 'between:'.$limites['minimo'].','.$limites['maximo']],
                "lecturas.$i.instante" => ['required', 'string'],
                "lecturas.$i.origen" => ['required', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))],
                "lecturas.$i.fuente" => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/D'],
            ])->validate();
            $instante = ParametrosConsulta::instante($fila['instante']);
            if ($instante->getTimestamp() > now()->timestamp) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'No se pueden registrar lecturas del futuro.');
            }
            $normalizadas[] = ['codigo' => $fila['punto'], 'metrica' => $metrica, 'valor' => round((float) $fila['valor'], 4),
                'instante' => $instante->format('Y-m-d H:i:s'), 'hora_utc' => $instante->format('Y-m-d H:00:00'), 'origen' => $fila['origen'], 'fuente' => $fila['fuente']];
        }

        $operacion = function () use ($normalizadas, $actor, $comprobar): array {
            $nuevas = 0;
            $paraAlertas = [];
            $puntos = PuntoAmbiental::whereIn('codigo', array_column($normalizadas, 'codigo'))->orderBy('id')->lockForUpdate()->get()->keyBy('codigo');
            foreach ($normalizadas as $fila) {
                $punto = $puntos->get($fila['codigo']);
                if (! $punto || ! $punto->activo) {
                    throw new ErrorConsulta('PUNTO_INACTIVO', 'El punto no está activo.', 422);
                }
                $identidad = ['punto_id' => $punto->id, 'metrica' => $fila['metrica'], 'instante' => $fila['instante'], 'origen' => $fila['origen'], 'fuente' => $fila['fuente']];
                $lectura = LecturaAmbiental::firstOrCreate($identidad, ['valor' => $fila['valor'], 'hora_utc' => $fila['hora_utc'],
                    'latitud' => $punto->latitud, 'longitud' => $punto->longitud, 'registrado_por' => $actor]);
                if (abs($lectura->valor - $fila['valor']) > 0.00001) {
                    throw new ErrorConsulta('LECTURA_EN_CONFLICTO', 'Ya existe otra lectura para ese instante y origen. Se conservó el dato anterior.', 409);
                }
                if ($lectura->wasRecentlyCreated) {
                    $nuevas++;
                    $paraAlertas[] = $lectura->setRelation('punto', $punto);
                }
            }
            $resultado = ['nuevas' => $nuevas, 'repetidas' => count($normalizadas) - $nuevas];
            if ($comprobar) {
                throw new LoteComprobado($resultado);
            }
            app(EvaluarAlertas::class)->ejecutar($paraAlertas);

            return $resultado;
        };
        // SQLite no implementa FOR UPDATE. Repetir el lote completo tras BUSY/SNAPSHOT,
        // con espera acotada, permite volver a leer la identidad después del otro commit.
        for ($intento = 0; ; $intento++) {
            try {
                return DB::transaction($operacion, DB::getDriverName() === 'sqlite' ? 1 : 3);
            } catch (LoteComprobado $e) {
                return $e->resultado;
            } catch (PDOException $e) {
                // firstOrCreate puede envolver BUSY en DeadlockException al usar un savepoint.
                $causa = $e;
                while ($causa->getPrevious() instanceof PDOException) {
                    $causa = $causa->getPrevious();
                }
                if (DB::getDriverName() !== 'sqlite' || ! in_array((int) ($causa->errorInfo[1] ?? 0), [5, 6, 517], true) || $intento >= 5 || DB::transactionLevel() > 0) {
                    throw $e;
                }
                usleep(25000 * (2 ** $intento));
            }
        }
    }
}
