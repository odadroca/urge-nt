<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptVersion extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'prompt_id',
        'branch_id',
        'version_number',
        'branch_version_number',
        'content',
        'commit_message',
        'variables',
        'variable_metadata',
        'includes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'variable_metadata' => 'array',
        'includes' => 'array',
        'created_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    protected static function booted(): void
    {
        static::updating(function (PromptVersion $version) {
            // Allow only archived_at changes
            $dirty = $version->getDirty();
            $allowed = ['archived_at'];
            $disallowed = array_diff(array_keys($dirty), $allowed);
            if (! empty($disallowed)) {
                throw new \LogicException('PromptVersion is immutable. Only archived_at can be modified.');
            }
        });

        static::creating(function (PromptVersion $version) {
            if (empty($version->created_at)) {
                $version->created_at = now();
            }
        });
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }

    public function branch()
    {
        return $this->belongsTo(PromptBranch::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results()
    {
        return $this->hasMany(Result::class)->orderByDesc('created_at');
    }
}
