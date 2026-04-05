<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PromptBranch extends Model
{
    protected $fillable = [
        'prompt_id',
        'name',
        'head_version_id',
        'forked_from_version_id',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (PromptBranch $branch) {
            $branch->name = Str::slug($branch->name);
        });
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }

    public function headVersion()
    {
        return $this->belongsTo(PromptVersion::class, 'head_version_id');
    }

    public function forkedFromVersion()
    {
        return $this->belongsTo(PromptVersion::class, 'forked_from_version_id');
    }

    public function versions()
    {
        return $this->hasMany(PromptVersion::class, 'branch_id')
            ->orderByDesc('branch_version_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
