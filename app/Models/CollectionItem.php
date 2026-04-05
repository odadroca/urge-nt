<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CollectionItem extends Model
{
    protected $fillable = [
        'collection_id',
        'item_type',
        'item_id',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }
}
