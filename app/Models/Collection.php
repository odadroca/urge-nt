<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Collection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Collection $collection) {
            if (empty($collection->slug)) {
                $base = Str::slug($collection->title);
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $counter++;
                }
                $collection->slug = $slug;
            }
        });
    }

    public function items()
    {
        return $this->hasMany(CollectionItem::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shareLinks()
    {
        return $this->hasMany(CollectionShareLink::class);
    }

    /**
     * CollectionItem rows in OTHER collections that reference THIS collection.
     */
    public function parentItems()
    {
        return CollectionItem::where('item_type', 'collection')
            ->where('item_id', $this->id);
    }
}
