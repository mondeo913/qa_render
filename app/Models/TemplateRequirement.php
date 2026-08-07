<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TemplateRequirement extends Model {
    protected $fillable = ['template_id','code','name','description','responsible_unit_id','responsible_role_code','required','requires_validation','requires_signature','min_files','max_files','max_size_mb','allowed_extensions','sort_order','active'];
    protected $casts = ['required'=>'boolean','requires_validation'=>'boolean','requires_signature'=>'boolean','allowed_extensions'=>'array','active'=>'boolean'];
    public function template(): BelongsTo { return $this->belongsTo(EvidenceTemplate::class,'template_id'); }
    public function responsibleUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'responsible_unit_id'); }
}
