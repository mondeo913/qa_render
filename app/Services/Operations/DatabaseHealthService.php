<?php
namespace App\Services\Operations;
use Illuminate\Support\Facades\DB;

final class DatabaseHealthService {
 public function inspect():array {
  $started=microtime(true);
  $version=DB::selectOne('select version() as version')->version;
  $connections=DB::selectOne("select count(*)::int total, count(*) filter(where state='active')::int active from pg_stat_activity where datname=current_database()");
  $size=DB::selectOne("select pg_database_size(current_database())::bigint bytes");
  return [
   'status'=>'UP','latency_ms'=>round((microtime(true)-$started)*1000,2),'version'=>$version,
   'connections_total'=>$connections->total,'connections_active'=>$connections->active,'size_bytes'=>(int)$size->bytes
  ];
 }
}
