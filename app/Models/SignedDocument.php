<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SignedDocument extends Model {
    protected $fillable = ['scheduled_load_id','folder_id','uploaded_by','document_type','signer_name','signer_position','signed_on','official_number','observations','active'];
    protected $casts = ['signed_on'=>'date','active'=>'boolean'];
    public function scheduledLoad(): BelongsTo { return $this->belongsTo(ScheduledLoad::class); }
    public function files(): HasMany { return $this->hasMany(EvidenceFile::class); }
}
