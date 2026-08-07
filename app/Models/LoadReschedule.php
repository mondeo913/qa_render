<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LoadReschedule extends Model {
    protected $fillable = ['scheduled_load_id','suspension_id','old_open_at','old_close_at','new_open_at','new_close_at','reason','retroactive','status','reprogrammed_by','reprogrammed_at','notification_sent_at'];
    protected $casts = ['old_open_at'=>'datetime','old_close_at'=>'datetime','new_open_at'=>'datetime','new_close_at'=>'datetime','retroactive'=>'boolean','reprogrammed_at'=>'datetime','notification_sent_at'=>'datetime'];
    public function scheduledLoad(): BelongsTo { return $this->belongsTo(ScheduledLoad::class); }
}
