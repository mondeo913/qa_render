<?php
namespace App\Services\Operations;
use Illuminate\Support\Facades\Storage;

final class StorageCapacityService {
 public function inspect():array {
  $disk=config('siget.repository_disk','local');
  $adapter=Storage::disk($disk);
  $files=0;$bytes=0;
  foreach($adapter->allFiles() as $path){$files++;$bytes+=$adapter->size($path);}
  return ['disk'=>$disk,'files'=>$files,'used_bytes'=>$bytes,'status'=>'UP'];
 }
}
