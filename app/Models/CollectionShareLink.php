<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionShareLink extends Model
{
    protected $fillable = [
        'collection_id',
        'token',
        'label',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count'     => 'integer',
    ];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function recordAccess(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function getUrl(): string
    {
        return url("/share/{$this->token}");
    }
}
