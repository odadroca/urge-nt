<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphPosition extends Model
{
    protected $fillable = ['user_id', 'node_type', 'node_id', 'x', 'y'];

    protected $casts = [
        'node_id' => 'integer',
        'x' => 'float',
        'y' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function bulkUpsert(int $userId, array $positions): int
    {
        $rows = array_map(fn (array $pos) => [
            'user_id' => $userId,
            'node_type' => $pos['node_type'],
            'node_id' => $pos['node_id'],
            'x' => $pos['x'],
            'y' => $pos['y'],
            'updated_at' => now(),
            'created_at' => now(),
        ], $positions);

        return self::upsert(
            $rows,
            ['user_id', 'node_type', 'node_id'],
            ['x', 'y', 'updated_at']
        );
    }
}
