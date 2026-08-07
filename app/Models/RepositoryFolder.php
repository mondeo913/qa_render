<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class RepositoryFolder extends Model {
    protected $fillable = ['parent_id','contracting_agency_id','organizational_unit_id','scheduled_load_id','folder_type','name','path_key','created_by'];
    public function children(): HasMany { return $this->hasMany(self::class,'parent_id'); }
}
