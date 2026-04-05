<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug', 'color'];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $base = Str::slug($category->name);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $counter++;
                }
                $category->slug = $slug;
            }
        });
    }

    public function prompts()
    {
        return $this->hasMany(Prompt::class);
    }

    public function getColorHexAttribute(): string
    {
        $map = [
            'gray' => '#6b7280', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b',
            'yellow' => '#eab308', 'lime' => '#84cc16', 'green' => '#22c55e', 'emerald' => '#10b981',
            'teal' => '#14b8a6', 'cyan' => '#06b6d4', 'sky' => '#0ea5e9', 'blue' => '#3b82f6',
            'indigo' => '#6366f1', 'violet' => '#8b5cf6', 'purple' => '#a855f7', 'fuchsia' => '#d946ef',
            'pink' => '#ec4899', 'rose' => '#f43f5e',
        ];

        return $map[$this->color] ?? '#6b7280';
    }
}
