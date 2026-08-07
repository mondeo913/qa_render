<?php
namespace App\Services\Operations;
use Illuminate\Support\Facades\DB;

final class QueueMonitorService {
 public function inspect():array {
  return [
   'pending'=>(int)DB::table('jobs')->count(),
   'failed'=>(int)DB::table('failed_jobs')->count(),
   'oldest_pending_seconds'=>(int)(DB::table('jobs')->min('available_at') ? now()->timestamp-DB::table('jobs')->min('available_at') : 0),
  ];
 }
}
