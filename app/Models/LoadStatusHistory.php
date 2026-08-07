<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadStatusHistory extends Model
{
    protected $table = 'load_status_history';

    protected $fillable = [
        'scheduled_load_id',
        'old_status',
        'new_status',
        'changed_by',
        'reason',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function scheduledLoad(): BelongsTo
    {
        return $this->belongsTo(ScheduledLoad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
