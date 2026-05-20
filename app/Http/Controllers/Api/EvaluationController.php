<?php

namespace App\Http\Controllers\Api;

use App\Models\Result;
use App\Services\AuthorizationService;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends ApiController
{
    public function __construct(private EvaluationService $evaluationService) {}

    public function evaluate(Request $request, Result $result): JsonResponse
    {
        $this->authorizeResultAccess($request, $result, 'evaluate');

        $providerOverride = null;
        if ($providerName = $request->input('provider')) {
            $providerOverride = \App\Models\LlmProvider::where('name', 'like', $providerName)
                ->where('is_active', true)
                ->first();
            if (!$providerOverride) {
                return $this->error("Provider '{$providerName}' not found or inactive.", 400);
            }
        }

        $evalResult = $this->evaluationService->evaluate($result, $request->user(), $providerOverride);

        if (isset($evalResult['error'])) {
            return $this->error($evalResult['error'], 400);
        }

        return $this->success($evalResult, 201);
    }

    public function index(Request $request, Result $result): JsonResponse
    {
        $this->authorizeResultAccess($request, $result, 'view');

        return $this->success($this->evaluationService->listVersions($result->id));
    }

    public function latest(Request $request, Result $result): JsonResponse
    {
        $this->authorizeResultAccess($request, $result, 'view');

        return $this->success($this->evaluationService->getEvaluations($result->id));
    }

    public function show(Request $request, Result $result, int $version): JsonResponse
    {
        $this->authorizeResultAccess($request, $result, 'view');

        return $this->success($this->evaluationService->getEvaluations($result->id, $version));
    }

    private function authorizeResultAccess(Request $request, Result $result, string $ability): void
    {
        $user = $request->user();
        $result->loadMissing('prompt');

        if (!AuthorizationService::userCanSeeResult($user, $result)) {
            abort(404);
        }

        if ($result->prompt) {
            AuthorizationService::enforceApiKeyScope($request, $result->prompt);
        }

        if (!$user->can($ability, $result)) {
            abort(403, "You do not have permission to {$ability} this result.");
        }
    }
}
