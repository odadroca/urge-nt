<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pipeline extends Model
{
    protected $table = 'pipelines';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pipeline $pipeline) {
            if (empty($pipeline->slug)) {
                $base = Str::slug($pipeline->name);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $counter++;
                }
                $pipeline->slug = $slug;
            }
        });
    }

    public function channels()
    {
        return $this->hasMany(PipelineChannel::class, 'pipeline_id')->orderBy('sort_order');
    }

    public function parallelChannels()
    {
        return $this->hasMany(PipelineChannel::class, 'pipeline_id')->where('trigger', 'parallel')->orderBy('sort_order');
    }

    public function synthesisChannel()
    {
        return $this->hasOne(PipelineChannel::class, 'pipeline_id')->where('trigger', 'synthesis');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
