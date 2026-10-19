<?php
namespace App\Models;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class ScheduledLoad extends Model {
    public const RETROACTIVE_POLICY_YEAR = 2026;

    use HasFactory;
    protected $fillable = ['calendar_import_id','calendar_import_row_id','contracting_agency_id','template_id','title','period_label','original_open_at','original_close_at','effective_open_at','effective_close_at','status','traffic_light','is_blocked','block_reason','retroactive','delivered_at','validated_at','closed_at','validated_by','closed_by','row_version','metadata','priority','completion_percentage','accounting_notified_at'];
    protected $casts = [
        'original_open_at'=>'datetime','original_close_at'=>'datetime','effective_open_at'=>'datetime','effective_close_at'=>'datetime',
        'delivered_at'=>'datetime','validated_at'=>'datetime','closed_at'=>'datetime','is_blocked'=>'boolean','retroactive'=>'boolean',
        'status'=>ScheduledLoadStatus::class,'traffic_light'=>TrafficLight::class,'metadata'=>'array','completion_percentage'=>'decimal:2','accounting_notified_at'=>'datetime'
    ];
    /**
     * Temporary 2026 exception for registering deliveries tracked manually before SIGET was ready.
     * Workflow and audit timestamps must still use their actual execution time.
     */
    public function isRetroactiveBackfillActive(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();
        $metadata = $this->metadata ?? [];

        return $this->retroactive
            && (bool) ($metadata['retroactive_registration'] ?? false)
            && (int) ($metadata['retroactive_policy_year'] ?? 0) === self::RETROACTIVE_POLICY_YEAR
            && $moment->year === self::RETROACTIVE_POLICY_YEAR
            && $this->original_open_at?->year === self::RETROACTIVE_POLICY_YEAR
            && $this->original_close_at !== null
            && $this->original_close_at->lt($moment);
    }

    /**
     * Date used by business workflow fields during 2026 backfill. Eloquent created_at/updated_at,
     * audit_logs and load_status_history intentionally retain the real execution timestamp.
     */
    public function operationalTimestamp(): CarbonInterface
    {
        if ($this->isRetroactiveBackfillActive() && $this->effective_close_at instanceof CarbonInterface) {
            return $this->effective_close_at;
        }

        return now();
    }

    public function agency(): BelongsTo { return $this->belongsTo(ContractingAgency::class,'contracting_agency_id'); }
    public function calendarImport(): BelongsTo { return $this->belongsTo(CalendarImport::class,'calendar_import_id'); }
    public function template(): BelongsTo { return $this->belongsTo(EvidenceTemplate::class,'template_id'); }
    public function deliverables(): HasMany { return $this->hasMany(ScheduledLoadDeliverable::class); }
    public function reschedules(): HasMany { return $this->hasMany(LoadReschedule::class); }
    public function institutionalReview(): HasOne { return $this->hasOne(InstitutionalReview::class); }
    public function signedDocuments(): HasMany { return $this->hasMany(SignedDocument::class); }
    public function closure(): HasOne { return $this->hasOne(LoadClosure::class); }
    public function reviewAssignments(): HasMany { return $this->hasMany(ReviewAssignment::class); }
    public function statusHistory(): HasMany { return $this->hasMany(LoadStatusHistory::class); }
    public function accountingNotice(): HasOne { return $this->hasOne(AccountingNotice::class); }
}
