<?php
namespace App\Console\Commands;
use App\Models\AlertRule;
use App\Notifications\OperationalIncidentNotification;
use App\Services\Alerts\AlertRuleEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class SigetEvaluateAlerts extends Command {
 protected $signature='siget:evaluate-alerts';protected $description='Evalúa reglas de alerta SIGET';
 public function handle(AlertRuleEvaluator $evaluator):int {
  $created=0;
  foreach(AlertRule::where('enabled',true)->cursor() as $rule){
   $incident=$evaluator->evaluate($rule);
   if(!$incident)continue;
   $recipients=collect($rule->recipients)->filter();
   if($recipients->isNotEmpty())Notification::route('mail',$recipients->all())->notify(new OperationalIncidentNotification($incident));
   $created++;
  }
  $this->info("Incidentes evaluados/generados: $created");return self::SUCCESS;
 }
}
