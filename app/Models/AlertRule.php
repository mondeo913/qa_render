<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class AlertRule extends Model {
 use HasUuids;
 protected $fillable=['code','name','metric_key','operator','threshold','severity','evaluation_window_minutes','cooldown_minutes','channels','recipients','enabled'];
 protected function casts():array{return ['threshold'=>'decimal:4','channels'=>'array','recipients'=>'array','enabled'=>'boolean'];}
}
