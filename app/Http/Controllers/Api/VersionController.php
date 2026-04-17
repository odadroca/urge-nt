<?php

namespace App\Http\Controllers\Api;

use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends ApiController
{
    use ResolvesPrompts;

    public function __construct(private VersioningService $versioningService) {}

    public function index(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        return $this->paginated(
            $prompt->versions()->getQuery(),
            $request
        );
    }

    public function store(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'content'           => 'required|string',
            'commit_message'    => 'nullable|string|max:500',
            'variable_metadata' => 'nullable|array',
        ]);

        $version = $this->versioningService->createVersion(
            $prompt,
            $validated,
            $request->user()
        );

        return $this->success($version, 201);
    }

    public function show(Request $request, string $username, string $promptSlug, int $version): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $promptVersion = $prompt->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        return $this->success($promptVersion);
    }

    public function archive(Request $request, string $username, string $promptSlug, int $version): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $promptVersion = $prompt->versions()->where('version_number', $version)->firstOrFail();

        if ($promptVersion->archived_at) {
            $promptVersion->archived_at = null;
            $promptVersion->save();
            return $this->success(['message' => "Version {$version} unarchived.", 'archived' => false]);
        }

        $promptVersion->archived_at = now();
        $promptVersion->save();
        return $this->success(['message' => "Version {$version} archived.", 'archived' => true]);
    }
}
