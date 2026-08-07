<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EvidenceReview extends Model {
    protected $table = 'evidence_reviews';
    protected $fillable = ['evidence_id','reviewer_id','decision','comments','review_type'];
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
