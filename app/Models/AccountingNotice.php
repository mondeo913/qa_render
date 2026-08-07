<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingNotice extends Model
{
    protected $fillable = [
        'scheduled_load_id',
        'recipients',
        'status',
        'sent_at',
        'payload',
        'failure_message',
    ];

    protected $casts = [
        'recipients' => 'array',
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function scheduledLoad(): BelongsTo
    {
        return $this->belongsTo(ScheduledLoad::class);
    }
}
