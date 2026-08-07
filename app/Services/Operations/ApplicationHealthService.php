<?php
namespace App\Services\Operations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

final class ApplicationHealthService {
 public function inspect():array {
  $cacheKey='siget:health:'.uniqid();
  Cache::put($cacheKey,'ok',10);
  $cacheOk=Cache::get($cacheKey)==='ok';
  Cache::forget($cacheKey);
  return [
   'app_env'=>app()->environment(),'debug'=>(bool)config('app.debug'),'cache'=>$cacheOk?'UP':'DOWN',
   'queue_connection'=>config('queue.default'),'mail_transport'=>config('mail.default'),'time'=>now()->toIso8601String()
  ];
 }
}
