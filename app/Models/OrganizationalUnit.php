<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class OrganizationalUnit extends Model {
    protected $fillable = ['contracting_agency_id','parent_id','code','name','unit_type','active'];
    protected $casts = ['active'=>'boolean'];
    public function agency(): BelongsTo { return $this->belongsTo(ContractingAgency::class,'contracting_agency_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class,'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class,'parent_id'); }
    public function deliverables(): HasMany { return $this->hasMany(ScheduledLoadDeliverable::class); }
}
