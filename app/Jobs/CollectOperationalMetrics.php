<?php
namespace App\Jobs;
use App\Services\Operations\HealthAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CollectOperationalMetrics implements ShouldQueue {
 use Queueable;
 public function handle(HealthAggregationService $service):void{$service->collect();}
}
