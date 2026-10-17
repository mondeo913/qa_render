<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Role extends Model {
    use HasFactory;
    protected $fillable = ['code','name','description','active'];
    protected $casts = ['active'=>'boolean'];
    protected static function booted(): void {
        static::updated(function (Role $role) {
            if ($role->wasChanged('active') && !$role->active && \Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                \Laravel\Sanctum\PersonalAccessToken::where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', User::where('role_id', $role->id)->select('id'))->delete();
            }
        });
    }
    public function permissions(): BelongsToMany {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }
}
