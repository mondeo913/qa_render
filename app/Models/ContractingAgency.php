<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ContractingAgency extends Model {
    use HasFactory;
    protected $fillable = ['code','name','legal_name','email_domain','active','metadata'];
    protected $casts = ['active'=>'boolean','metadata'=>'array'];
    public function units(): HasMany { return $this->hasMany(OrganizationalUnit::class); }
    public function templates(): HasMany { return $this->hasMany(EvidenceTemplate::class); }
    public function scheduledLoads(): HasMany { return $this->hasMany(ScheduledLoad::class); }
}
