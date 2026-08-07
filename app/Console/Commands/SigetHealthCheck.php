<?php
namespace App\Console\Commands;
use App\Models\OperationalMetric;
use App\Services\Operations\HealthAggregationService;
use Illuminate\Console\Command;

final class SigetHealthCheck extends Command {
 protected $signature='siget:health {--json}';protected $description='Verifica la salud operativa de SIGET';
 public function handle(HealthAggregationService $service):int {
  $data=$service->collect();
  foreach([
   ['database.latency_ms',$data['database']['latency_ms'],'ms'],
   ['database.connections_active',$data['database']['connections_active'],'connections'],
   ['database.size_bytes',$data['database']['size_bytes'],'bytes'],
   ['queue.pending',$data['queue']['pending'],'jobs'],
   ['queue.failed',$data['queue']['failed'],'jobs'],
   ['storage.used_bytes',$data['storage']['used_bytes'],'bytes'],
   ['storage.files',$data['storage']['files'],'files'],
  ] as [$key,$value,$unit]) OperationalMetric::create(['metric_key'=>$key,'metric_value'=>$value,'unit'=>$unit,'dimensions'=>[],'collected_at'=>now()]);
  $this->line($this->option('json')?json_encode($data,JSON_PRETTY_PRINT):"SIGET: {$data['overall_status']}");
  return $data['overall_status']==='UP'?self::SUCCESS:self::FAILURE;
 }
}
