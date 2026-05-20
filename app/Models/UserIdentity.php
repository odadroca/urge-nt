<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email',
        'email_verified',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
