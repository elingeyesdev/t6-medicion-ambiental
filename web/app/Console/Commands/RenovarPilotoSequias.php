<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\AlmacenPublicacionesSequias;
use App\Services\Sequias\PreparadorConsultaSequias;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RenovarPilotoSequias extends Command
{
    protected $signature = 'sequias:renovar-piloto {--ahora : Consultar aunque la publicación siga vigente}';

    protected $description = 'Renovar la muestra piloto ERA5 con la base histórica local';

    public function handle(AlmacenPublicacionesSequias $almacen, PreparadorConsultaSequias $preparador): int
    {
        $muestra = 'scz-piloto-01';
        $ahora = now()->utc();
        // ERA5 llega con cinco días de retraso. El día de corte debe cubrir el fin de mes.
        $mes = $ahora->copy()->subDays(5)->startOfMonth()->subMonth()->format('Y-m');
        $contexto = ['muestra' => $muestra, 'mesFinal' => $mes];

        try {
            $anterior = $almacen->leer('consultas', PreparadorConsultaSequias::clave($muestra, $mes));
            if (! $this->option('ahora') && isset($anterior['vigenteHasta'])
                && strtotime($anterior['vigenteHasta']) > $ahora->copy()->addHours(6)->getTimestamp()) {
                $this->info("La publicación de {$mes} sigue vigente. Próxima comprobación en una hora.");

                return self::SUCCESS;
            }

            $resultado = $preparador->ejecutar($muestra, $mes, null, null, consultar: true, renovar: true, exigirIndice: true);
            $contexto['vigenteHasta'] = $resultado['datos']['vigenteHasta'];
            Log::info('Sequías: publicación piloto renovada.', $contexto);
            $this->info("Publicación de {$mes} renovada hasta {$contexto['vigenteHasta']}.");

            return self::SUCCESS;
        } catch (SeriePrecipitacionInvalida $error) {
            Log::warning('Sequías: falló la renovación del piloto.', $contexto + ['codigo' => $error->codigo]);
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
