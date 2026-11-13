<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\AlmacenPublicacionesSequias;
use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\PreparadorConsultaSequias;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RenovarPilotoSequias extends Command
{
    protected $signature = 'sequias:renovar-piloto {--ahora : Consultar aunque la publicación siga vigente} {--provincia= : Renovar los puntos preparados de la provincia}';

    protected $description = 'Renovar la muestra piloto ERA5 con la base histórica local';

    public function handle(AlmacenPublicacionesSequias $almacen, PreparadorConsultaSequias $preparador, CatalogoMuestrasSequias $catalogo): int
    {
        $ahora = now()->utc();
        // ERA5 llega con cinco días de retraso. El día de corte debe cubrir el fin de mes.
        $mes = $ahora->copy()->subDays(5)->startOfMonth()->subMonth()->format('Y-m');
        $provincia = $this->option('provincia');
        try {
            $muestras = $provincia === null
                ? ['scz-piloto-01']
                : array_column($catalogo->consultar(provincia: $provincia)['datos'], 'identificador');
        } catch (SeriePrecipitacionInvalida $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $estado = self::SUCCESS;
        foreach ($muestras as $muestra) {
            $resultado = $this->renovar($muestra, $mes, $almacen, $preparador);
            if ($resultado !== self::SUCCESS) {
                $estado = $resultado;
            }
        }

        return $estado;
    }

    private function renovar(string $muestra, string $mes, AlmacenPublicacionesSequias $almacen, PreparadorConsultaSequias $preparador): int
    {
        $contexto = ['muestra' => $muestra, 'mesFinal' => $mes];

        try {
            $anterior = $almacen->leer('consultas', PreparadorConsultaSequias::clave($muestra, $mes));
            if (! $this->option('ahora') && isset($anterior['vigenteHasta'])
                && strtotime($anterior['vigenteHasta']) > now()->addHours(6)->getTimestamp()) {
                $this->info("La publicación de {$muestra} para {$mes} sigue vigente. Próxima comprobación en una hora.");

                return self::SUCCESS;
            }

            $resultado = $preparador->ejecutar($muestra, $mes, null, null, consultar: true, renovar: true, exigirIndice: true);
            $contexto['vigenteHasta'] = $resultado['datos']['vigenteHasta'];
            Log::info('Sequías: publicación piloto renovada.', $contexto);
            $this->info("Publicación de {$muestra} para {$mes} renovada hasta {$contexto['vigenteHasta']}.");

            return self::SUCCESS;
        } catch (SeriePrecipitacionInvalida $error) {
            Log::warning('Sequías: falló la renovación del piloto.', $contexto + ['codigo' => $error->codigo]);
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
