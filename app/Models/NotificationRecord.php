<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationRecord extends Model {
    protected $table = 'notifications';
    protected $fillable = ['user_id','scheduled_load_id','channel','status','subject','message','action_url','scheduled_for','sent_at','read_at','failure_message'];
    protected $casts = ['scheduled_for'=>'datetime','sent_at'=>'datetime','read_at'=>'datetime'];
}
