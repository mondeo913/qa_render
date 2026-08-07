<?php
namespace App\Console\Commands;
use App\Models\OperationalIncident;
use App\Services\Operations\QueueMonitorService;
use Illuminate\Console\Command;

final class SigetQueueWatch extends Command {
 protected $signature='siget:queue-watch {--max-pending=100} {--max-age=900}';protected $description='Detecta saturación de colas';
 public function handle(QueueMonitorService $service):int {
  $q=$service->inspect();
  if($q['pending']>(int)$this->option('max-pending')||$q['oldest_pending_seconds']>(int)$this->option('max-age')){
   OperationalIncident::firstOrCreate(['code'=>'QUEUE-'.now()->format('YmdH')],[
    'title'=>'Cola SIGET saturada','severity'=>'HIGH','status'=>'OPEN','source'=>'queue-watch',
    'description'=>json_encode($q),'opened_at'=>now(),'metadata'=>$q
   ]);
   $this->error('Cola saturada');return self::FAILURE;
  }
  $this->info('Cola saludable');return self::SUCCESS;
 }
}
