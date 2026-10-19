<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('model:prune')->daily();
Schedule::command('siget:open-rescheduled-loads')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('siget:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('siget:refresh-indicators')->hourly()->withoutOverlapping();

Schedule::command('siget:health')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('siget:queue-watch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('siget:evaluate-alerts')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
// El respaldo completo se ejecuta una vez al mes mediante siget-backup.timer en VM1.
// El comando de vigilancia comprueba antigüedad; no genera respaldos adicionales.
if (app()->environment('production')) Schedule::command('siget:backup-status')->dailyAt('09:00')->withoutOverlapping();
