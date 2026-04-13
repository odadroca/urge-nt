<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';

    protected $fillable = [
        'code', 'client_id', 'user_id', 'redirect_uri',
        'scope', 'code_challenge', 'code_challenge_method',
        'resource', 'expires_at',
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
}
