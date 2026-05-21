<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Prompt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'category_id',
        'tags',
        'visibility',
        'pinned_version_id',
        'default_branch_id',
        'created_by',
        'derived_from_prompt_id',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Prompt $prompt) {
            if (empty($prompt->slug)) {
                $base = Str::slug($prompt->name);
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()
                    ->where('slug', $slug)
                    ->where('created_by', $prompt->created_by)
                    ->exists()
                ) {
                    $slug = $base.'-'.$counter++;
                }
                $prompt->slug = $slug;
            }
        });
    }

    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'derived_from_prompt_id');
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(Prompt::class, 'derived_from_prompt_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function pinnedVersion()
    {
        return $this->belongsTo(PromptVersion::class, 'pinned_version_id');
    }

    public function branches()
    {
        return $this->hasMany(PromptBranch::class);
    }

    public function defaultBranch()
    {
        return $this->belongsTo(PromptBranch::class, 'default_branch_id');
    }

    public function activeVersion()
    {
        if ($this->pinned_version_id) {
            return $this->pinnedVersion();
        }

        return $this->hasOne(PromptVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion()
    {
        return $this->hasOne(PromptVersion::class)->orderByDesc('version_number');
    }

    public function versions()
    {
        return $this->hasMany(PromptVersion::class)->orderByDesc('version_number');
    }

    public function results()
    {
        return $this->hasMany(Result::class)->orderByDesc('created_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'prompt_team')
            ->withTimestamps();
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function isShared(): bool
    {
        return $this->visibility === 'shared';
    }

    public function scopeVisibleTo($query, User $user): void
    {
        if ($user->isAdmin()) {
            return; // Admins see everything
        }

        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('teams', function ($tq) use ($user) {
                    $tq->whereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
                });
        });
    }

    public function workspaceUrl(): string
    {
        return '/app/workspace/'.$this->creator->slug.'/'.$this->slug;
    }

    public function isFragment(): bool
    {
        return $this->type === 'fragment';
    }

    public function getActiveVersionAttribute()
    {
        // 1. Pinned version always wins
        if ($this->pinned_version_id) {
            if ($this->relationLoaded('pinnedVersion')) {
                return $this->pinnedVersion;
            }

            return $this->pinnedVersion;
        }

        // 2. Default branch HEAD
        if ($this->default_branch_id) {
            $branch = $this->relationLoaded('defaultBranch')
                ? $this->defaultBranch
                : $this->defaultBranch()->first();
            if ($branch) {
                return $branch->relationLoaded('headVersion')
                    ? $branch->headVersion
                    : $branch->headVersion()->first();
            }
        }

        // 3. Legacy fallback: highest version_number
        if ($this->relationLoaded('latestVersion')) {
            return $this->latestVersion;
        }

        return $this->latestVersion;
    }
}
