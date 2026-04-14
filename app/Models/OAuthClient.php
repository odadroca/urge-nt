<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthClient extends Model
{
    protected $table = 'oauth_clients';

    protected $fillable = [
        'client_id', 'client_name', 'redirect_uris',
        'grant_types', 'response_types', 'token_endpoint_auth_method', 'scope',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'grant_types' => 'array',
        'response_types' => 'array',
    ];
}
