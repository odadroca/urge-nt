<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'key_hash',
        'key_preview',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prompts()
    {
        return $this->belongsToMany(Prompt::class, 'api_key_prompt');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }
}
