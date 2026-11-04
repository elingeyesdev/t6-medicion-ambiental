<?php

namespace App\Services\Simulaciones;

use App\Models\SimulacionContinua;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ContinuarSimulacion
{
    public function __construct(private MotorContinuo $motor, private CatalogoAmbiental $catalogo) {}

    public function estado(int $usuario): array
    {
        return DB::transaction(function () use ($usuario) {
            $s = SimulacionContinua::lockForUpdate()->find(1);
            if (! $s) {
                return ['estado' => 'sin_preparar', 'revision' => 0, 'propia' => false, 'origen' => 'simulado', 'puntos' => []];
            }
            [$c, $pendientes, $pasos] = $this->avanzar($s);
            if ($pasos > 0) {
                $s->contenido = $c;
                $s->revision++;
                $s->save();
            }

            return $s->paraCliente($usuario) + ['pendientes' => $pendientes];
        });
    }

    public function accion(array $entrada, int $usuario): array
    {
        Validator::make(['entrada' => $entrada], ['entrada' => ['array:accion,revision,semilla,intervalo_ms,punto,valores']])->validate();
        $d = Validator::make($entrada, [
            'accion' => ['required', Rule::in(['preparar', 'iniciar', 'pausar', 'detener', 'reiniciar', 'inyectar'])],
            'revision' => ['required', 'integer', 'min:0'],
            'semilla' => ['required_if:accion,preparar', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'intervalo_ms' => ['sometimes', 'integer', 'between:1000,60000'],
            'punto' => ['required_if:accion,inyectar', 'string', 'max:60'],
            'valores' => ['required_if:accion,inyectar', 'array:'.implode(',', MotorContinuo::METRICAS), 'min:1'],
            'valores.*' => ['required', 'numeric'],
        ])->validate();

        try {
            return DB::transaction(function () use ($d, $usuario) {
                $s = SimulacionContinua::lockForUpdate()->find(1);
                if (($s?->revision ?? 0) !== (int) $d['revision']) {
                    throw new ErrorConsulta('SESION_CAMBIADA', 'La simulación cambió. Revisa su estado y vuelve a intentarlo.', 409);
                }
                $accion = $d['accion'];
                if ($s && $s->usuario_id !== $usuario && ! ($accion === 'preparar' && $s->estado === 'detenida')) {
                    abort(404);
                }
                if ($accion === 'preparar') {
                    if ($s && ! in_array($s->estado, ['lista', 'detenida'])) {
                        throw new ErrorConsulta('ESTADO_INVALIDO', 'Detén la simulación antes de preparar otra.', 409);
                    }
                    $s ??= new SimulacionContinua(['id' => 1, 'revision' => 0]);
                    $s->usuario_id = $usuario;
                    $s->contenido = $this->inicial($d['semilla'], $d['intervalo_ms'] ?? config('simulaciones.intervalo_ms'));
                    $s->estado = 'lista';
                } else {
                    abort_unless($s, 404);
                    [$c, $pendientes] = $this->avanzar($s);
                    if (($accion === 'iniciar' && ! in_array($s->estado, ['lista', 'pausada', 'detenida'])) ||
                        ($accion === 'pausar' && $s->estado !== 'activa') ||
                        ($accion === 'inyectar' && ! in_array($s->estado, ['activa', 'pausada']))) {
                        throw new ErrorConsulta('ESTADO_INVALIDO', 'Ese control no está disponible en el estado actual.', 409);
                    }
                    if ($pendientes > 0 && in_array($accion, ['iniciar', 'inyectar'])) {
                        throw new ErrorConsulta('RECORRIDO_PENDIENTE', 'Se está recuperando el recorrido. Espera a que termine para aplicar este cambio.', 409);
                    }
                    if ($accion === 'reiniciar') {
                        $c = $this->inicial($c['semilla'], $c['intervalo_ms']);
                    } elseif ($accion === 'inyectar') {
                        $indice = array_search($d['punto'], array_column($c['puntos'], 'codigo'), true);
                        if ($indice === false) {
                            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige una zona de esta simulación.');
                        }
                        foreach ($d['valores'] as $k => $valor) {
                            $limite = $this->catalogo->metricas()[$k];
                            if (! is_finite((float) $valor) || $valor < $limite['minimo'] || $valor > $limite['maximo']) {
                                throw new ErrorConsulta('FILTROS_INVALIDOS', 'El valor supera los límites de la variable.');
                            }
                            $c['puntos'][$indice]['valores'][$k] = (float) $valor;
                            $c['puntos'][$indice]['inyecciones'][$k] = true;
                        }
                        $c['ajustes'][] = ['punto' => $d['punto'], 'valores' => $d['valores'], 'instante' => $this->iso($this->ms()), 'paso' => $c['paso']];
                        $c['ajustes'] = array_slice($c['ajustes'], -20);
                    }
                    if ($accion === 'iniciar') {
                        $c['reloj_ms'] = $this->ms();
                        $c['cierre_ms'] = null;
                    } elseif (in_array($accion, ['pausar', 'detener']) && $s->estado === 'activa') {
                        // Fija el final del tramo activo: el trabajo pendiente se recupera sin saltarlo ni seguir avanzando el reloj.
                        $c['cierre_ms'] = $this->ms();
                    }
                    $s->estado = match ($accion) {
                        'iniciar' => 'activa', 'pausar' => 'pausada', 'detener' => 'detenida', 'reiniciar' => 'lista', default => $s->estado,
                    };
                    $s->contenido = $c;
                }
                $s->revision++;
                $s->save();

                return $s->paraCliente($usuario);
            });
        } catch (QueryException $e) {
            // La PK única protege también dos preparaciones simultáneas cuando aún no existe la fila.
            if (in_array((string) $e->getCode(), ['23000', '23505'])) {
                throw new ErrorConsulta('SESION_CAMBIADA', 'Otra pantalla preparó la simulación. Revisa su estado.', 409);
            }
            throw $e;
        }
    }

    private function avanzar(SimulacionContinua $s): array
    {
        $c = $s->contenido;
        $hasta = $s->estado === 'activa' ? $this->ms() : ($c['cierre_ms'] ?? $c['reloj_ms']);
        $pendientes = max(0, intdiv($hasta - $c['reloj_ms'], $c['intervalo_ms']));
        $pasos = min($pendientes, max(1, min(50, (int) config('simulaciones.pasos_por_consulta'))));
        for ($i = 0; $i < $pasos; $i++) {
            $c['reloj_ms'] += $c['intervalo_ms'];
            $c['paso']++;
            $c['puntos'] = $this->motor->siguiente($c['puntos'], $c['semilla'], $c['paso'], $c['reloj_ms']);
        }
        if ($pasos > 0) {
            $c['instante'] = $this->iso($c['reloj_ms']);
        }

        return [$c, $pendientes - $pasos, $pasos];
    }

    private function inicial(string $semilla, int $intervalo): array
    {
        return ['version' => (string) Str::ulid(), 'semilla' => $semilla, 'intervalo_ms' => $intervalo,
            'reloj_ms' => $this->ms(), 'instante' => $this->iso($this->ms()), 'paso' => 0,
            'metodo' => 'envirosense-continuo-v1', 'puntos' => $this->motor->inicial($semilla), 'ajustes' => []];
    }

    private function ms(): int
    {
        return (int) now()->getPreciseTimestamp(3);
    }

    private function iso(int $ms): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', intdiv($ms, 1000));
    }
}
