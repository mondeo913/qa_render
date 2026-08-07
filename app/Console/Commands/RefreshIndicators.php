<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshIndicators extends Command
{
    protected $signature = 'siget:refresh-indicators';
    protected $description = 'Refresca las vistas materializadas de indicadores.';

    public function handle(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_indicators');
        }
        $this->info('Indicadores actualizados.');
        return self::SUCCESS;
    }
}
