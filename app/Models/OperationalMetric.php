<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class OperationalMetric extends Model {
 use HasUuids;
 protected $fillable=['metric_key','metric_value','unit','dimensions','collected_at'];
 protected function casts():array{return ['metric_value'=>'decimal:4','dimensions'=>'array','collected_at'=>'datetime'];}
}
