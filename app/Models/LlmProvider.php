<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmProvider extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'api_key',
        'model',
        'endpoint',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['api_key'];

    public function isOllama(): bool
    {
        return $this->driver === 'ollama';
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }
}
