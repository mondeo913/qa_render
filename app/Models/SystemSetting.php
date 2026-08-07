<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'description', 'updated_by'];

    protected $casts = ['value' => 'array'];
}
