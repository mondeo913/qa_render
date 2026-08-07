<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    protected $fillable = ['code', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class)->orderBy('sort_order');
    }
}
