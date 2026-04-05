<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController
{
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    protected function paginated($query, Request $request, int $perPage = 20): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', $perPage), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
