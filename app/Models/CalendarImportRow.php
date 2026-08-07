<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CalendarImportRow extends Model {
    use HasFactory;
    protected $fillable = ['calendar_import_id','sheet_name','row_number','source_column','contracting_agency_code','organizational_unit_code','template_code','original_open_at','original_close_at','delivery_name','payload','is_valid','validation_messages'];
    protected $casts = ['original_open_at'=>'datetime','original_close_at'=>'datetime','payload'=>'array','is_valid'=>'boolean','validation_messages'=>'array'];
}
