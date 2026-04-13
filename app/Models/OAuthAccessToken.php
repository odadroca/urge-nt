<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAccessToken extends Model
{
    protected $table = 'oauth_access_tokens';

    protected $fillable = [
        'token', 'user_id', 'client_id', 'scope', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        $granted = explode(' ', $this->scope);
        return in_array($scope, $granted);
    }
}
