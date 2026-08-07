<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScope extends Model
{
    protected $fillable = [
        'user_id',
        'contracting_agency_id',
        'organizational_unit_id',
        'can_read',
        'can_write',
    ];

    protected $casts = [
        'can_read' => 'boolean',
        'can_write' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(ContractingAgency::class, 'contracting_agency_id');
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }
}
