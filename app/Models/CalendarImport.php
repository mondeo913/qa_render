<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CalendarImport extends Model {
    use HasFactory;
    protected $fillable = ['contracting_agency_id','uploaded_by','original_filename','storage_path','sha256','workbook_version','status','total_rows','valid_rows','error_rows','warnings','errors','confirmed_at'];
    protected $casts = ['warnings'=>'array','errors'=>'array','confirmed_at'=>'datetime'];
    public function rows(): HasMany { return $this->hasMany(CalendarImportRow::class); }
}
