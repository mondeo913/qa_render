<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class OperationalIncident extends Model {
 use HasUuids;
 protected $fillable=['code','title','severity','status','source','description','assigned_to','opened_at','acknowledged_at','resolved_at','resolution','metadata'];
 protected function casts():array{return ['opened_at'=>'datetime','acknowledged_at'=>'datetime','resolved_at'=>'datetime','metadata'=>'array'];}
}
