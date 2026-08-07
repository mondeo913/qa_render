<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LoadClosure extends Model {
    protected $fillable = ['scheduled_load_id','institutional_review_id','signed_document_id','closed_by','closed_at','closing_comment','package_sha256','integrity_manifest','closure_certificate_path','reopened','reopened_reason','reopened_by','reopened_at'];
    protected $casts = ['closed_at'=>'datetime','integrity_manifest'=>'array','reopened'=>'boolean','reopened_at'=>'datetime'];
}
