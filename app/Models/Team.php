<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Team extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $base = Str::slug($team->name);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $counter++;
                }
                $team->slug = $slug;
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners()
    {
        return $this->members()->wherePivot('role', 'owner');
    }

    public function prompts()
    {
        return $this->belongsToMany(Prompt::class, 'prompt_team')
            ->withTimestamps();
    }
}
