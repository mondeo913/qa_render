<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EvidenceCorrectionWindow extends Model
{
    protected $fillable = ['deliverable_id', 'authorized_by', 'reason', 'opens_at', 'closes_at', 'used_at', 'revoked_at', 'evidence_version'];
    protected $casts = ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
}
