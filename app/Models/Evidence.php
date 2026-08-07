<?php
namespace App\Models;
use App\Enums\DeliverableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Evidence extends Model {
    protected $table = 'evidences';

    protected $fillable = ['scheduled_load_id','deliverable_id','folder_id','submitted_by','title','description','status','current_version','submitted_at','validated_at','validated_by','revision_number'];
    protected $casts = ['status'=>DeliverableStatus::class,'submitted_at'=>'datetime','validated_at'=>'datetime'];
    public function scheduledLoad(): BelongsTo { return $this->belongsTo(ScheduledLoad::class); }
    public function deliverable(): BelongsTo { return $this->belongsTo(ScheduledLoadDeliverable::class); }
    public function files(): HasMany { return $this->hasMany(EvidenceFile::class); }
    public function reviews(): HasMany { return $this->hasMany(EvidenceReview::class); }
}
