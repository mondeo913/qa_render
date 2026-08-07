<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class MaintenanceWindow extends Model {
 use HasUuids;
 protected $fillable=['title','starts_at','ends_at','impact','status','approved_by','executed_by','rollback_plan','result'];
 protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime'];}
}
