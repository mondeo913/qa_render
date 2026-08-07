<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CalendarSuspension extends Model {
    protected $fillable = ['name','description','starts_at','ends_at','applies_to_all_agencies','contracting_agency_id','block_upload','exclude_from_compliance','active','created_by'];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime','applies_to_all_agencies'=>'boolean','block_upload'=>'boolean','exclude_from_compliance'=>'boolean','active'=>'boolean'];
}
