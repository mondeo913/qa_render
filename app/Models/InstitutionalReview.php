<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InstitutionalReview extends Model {
    protected $fillable = ['scheduled_load_id','institutional_link_id','evidences_correct','package_prepared_for_signature','observations','started_at','verified_at','validated_at'];
    protected $casts = ['evidences_correct'=>'boolean','package_prepared_for_signature'=>'boolean','started_at'=>'datetime','verified_at'=>'datetime','validated_at'=>'datetime'];
}
