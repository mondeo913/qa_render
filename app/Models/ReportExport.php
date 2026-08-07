<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    protected $fillable = [
        'requested_by',
        'report_type',
        'format',
        'filters',
        'status',
        'storage_path',
    ];

    protected $casts = ['filters' => 'array'];
}
