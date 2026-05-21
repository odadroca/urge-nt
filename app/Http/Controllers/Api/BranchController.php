<?php

namespace App\Http\Controllers\Api;

use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends ApiController
{
    use ResolvesPrompts;

    public function __construct(private VersioningService $versioningService) {}

    public function index(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $branches = $prompt->branches()
            ->withCount('versions')
            ->with('headVersion:id,version_number,branch_version_number,commit_message,created_at')
            ->get();

        return $this->success($branches);
    }

    public function store(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'from_version' => 'nullable|integer',
        ]);

        $fromVersion = null;
        if (! empty($validated['from_version'])) {
            $fromVersion = $prompt->versions()
                ->where('version_number', $validated['from_version'])
                ->firstOrFail();
        }

        $branch = $this->versioningService->createBranch(
            $prompt,
            $validated['name'],
            $request->user(),
            $fromVersion
        );

        return $this->success($branch->load('headVersion'), 201);
    }

    public function show(Request $request, string $username, string $promptSlug, string $branch): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $branchModel = $prompt->branches()
            ->where('name', $branch)
            ->withCount('versions')
            ->with(['headVersion', 'forkedFromVersion:id,version_number'])
            ->firstOrFail();

        return $this->success($branchModel);
    }

    public function destroy(Request $request, string $username, string $promptSlug, string $branch): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $branchModel = $prompt->branches()->where('name', $branch)->firstOrFail();

        if ($branchModel->is_default) {
            return $this->error('Cannot delete the default branch.', 422);
        }

        $this->versioningService->deleteBranch($branchModel);

        return $this->success(['deleted' => true]);
    }

    public function setDefault(Request $request, string $username, string $promptSlug, string $branch): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $branchModel = $prompt->branches()->where('name', $branch)->firstOrFail();

        $this->versioningService->setDefaultBranch($prompt, $branchModel);

        return $this->success(['default_branch' => $branchModel->name]);
    }
}
