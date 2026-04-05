<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PipelineTemplate extends Model
{
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
        static::creating(function (PipelineTemplate $template) {
            if (empty($template->slug)) {
                $base = Str::slug($template->name);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $counter++;
                }
                $template->slug = $slug;
            }
        });
    }

    public function channels()
    {
        return $this->hasMany(PipelineTemplateChannel::class)->orderBy('sort_order');
    }

    public function parallelChannels()
    {
        return $this->hasMany(PipelineTemplateChannel::class)->where('trigger', 'parallel')->orderBy('sort_order');
    }

    public function synthesisChannel()
    {
        return $this->hasOne(PipelineTemplateChannel::class)->where('trigger', 'synthesis');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
