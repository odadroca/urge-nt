<?php

namespace App\Http\Controllers\Api;

use App\Models\LlmProvider;
use Illuminate\Http\JsonResponse;

class LlmProviderController extends ApiController
{
    public function index(): JsonResponse
    {
        $providers = LlmProvider::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'driver', 'model']);

        return $this->success($providers);
    }
}
