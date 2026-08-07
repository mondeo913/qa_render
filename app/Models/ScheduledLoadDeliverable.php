<?php
namespace App\Models;
use App\Enums\DeliverableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ScheduledLoadDeliverable extends Model {
    protected $fillable = ['scheduled_load_id','template_requirement_id','organizational_unit_id','responsible_user_id','status','due_at','submitted_at','validated_at','validated_by','observations'];
    protected $casts = ['status'=>DeliverableStatus::class,'due_at'=>'datetime','submitted_at'=>'datetime','validated_at'=>'datetime'];
    public function scheduledLoad(): BelongsTo { return $this->belongsTo(ScheduledLoad::class); }
    public function templateRequirement(): BelongsTo { return $this->belongsTo(TemplateRequirement::class); }
    public function organizationalUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class); }
    public function evidences(): HasMany { return $this->hasMany(Evidence::class,'deliverable_id'); }
    public function responsibleUser(): BelongsTo { return $this->belongsTo(User::class,'responsible_user_id'); }
}
