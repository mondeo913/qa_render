<?php
namespace App\Services\Operations;

final class HealthAggregationService {
 public function __construct(
  private DatabaseHealthService $database,
  private QueueMonitorService $queue,
  private StorageCapacityService $storage,
  private ApplicationHealthService $application
 ){}
 public function collect():array {
  $data=[
   'application'=>$this->application->inspect(),'database'=>$this->database->inspect(),
   'queue'=>$this->queue->inspect(),'storage'=>$this->storage->inspect(),
  ];
  $data['overall_status']=$data['database']['status']==='UP' && $data['storage']['status']==='UP' ? 'UP':'DEGRADED';
  return $data;
 }
}
