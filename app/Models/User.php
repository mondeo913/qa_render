<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable {
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens;
    protected $fillable = ['role_id','contracting_agency_id','organizational_unit_id','name','email','password','status','metadata'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['password'=>'hashed','email_verified_at'=>'datetime','last_login_at'=>'datetime','metadata'=>'array'];
    protected static function booted(): void {
        static::updated(function (User $user) {
            if ($user->wasChanged(['password', 'status', 'role_id', 'contracting_agency_id', 'organizational_unit_id'])
                && \Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                $user->tokens()->delete();
            }
        });
    }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function agency(): BelongsTo { return $this->belongsTo(ContractingAgency::class,'contracting_agency_id'); }
    public function organizationalUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class); }
    public function scopes(): HasMany { return $this->hasMany(UserScope::class); }
    public function reviewAssignments(): HasMany { return $this->hasMany(ReviewAssignment::class, 'fiscalizador_id'); }
    public function canonicalIdentityId(): int { return (int) ($this->metadata['qa_merged_into'] ?? $this->id); }
    public function hasPermission(string $code): bool {
        return $this->status === 'ACTIVE' && $this->role?->active
            ? $this->role->permissions()->where('code',$code)->exists() : false;
    }
}
