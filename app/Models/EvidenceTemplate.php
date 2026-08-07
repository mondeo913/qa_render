<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class EvidenceTemplate extends Model {
    use HasFactory;
    protected $fillable = ['contracting_agency_id','code','name','description','version','active','requires_director_signature','allowed_signed_extensions','created_by'];
    protected $casts = ['active'=>'boolean','requires_director_signature'=>'boolean','allowed_signed_extensions'=>'array'];
    public function requirements(): HasMany { return $this->hasMany(TemplateRequirement::class,'template_id')->orderBy('sort_order'); }
}
