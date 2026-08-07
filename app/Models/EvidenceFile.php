<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EvidenceFile extends Model {
    protected $table = 'evidence_files';
    protected $fillable = ['evidence_id','signed_document_id','folder_id','uploaded_by','original_name','stored_name','storage_disk','storage_path','extension','mime_type','size_bytes','sha256','antivirus_status','version','metadata'];
    protected $casts = ['metadata'=>'array'];
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function signedDocument(): BelongsTo { return $this->belongsTo(SignedDocument::class); }
}
