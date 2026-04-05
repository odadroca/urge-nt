<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'version'   => '2.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
