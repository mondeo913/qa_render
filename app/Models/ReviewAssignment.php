<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAssignment extends Model
{
    protected $fillable = [
        'scheduled_load_id',
        'fiscalizador_id',
        'assigned_by',
        'active',
        'notes',
    ];

    protected $casts = ['active' => 'boolean'];

    public function scheduledLoad(): BelongsTo
    {
        return $this->belongsTo(ScheduledLoad::class);
    }

    public function fiscalizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fiscalizador_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
