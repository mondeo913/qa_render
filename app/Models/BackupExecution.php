<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class BackupExecution extends Model {
 use HasUuids;
 protected $fillable=['backup_type','status','started_at','finished_at','database_file','storage_file','database_sha256','storage_sha256','size_bytes','verified_at','error_message','metadata'];
 protected function casts():array{return ['started_at'=>'datetime','finished_at'=>'datetime','verified_at'=>'datetime','metadata'=>'array'];}
}
