<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use App\Models\CollectionShareLink;
use App\Services\ShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareLinkController extends ApiController
{
    public function __construct(
        private ShareLinkService $service,
    ) {}

    public function index(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $links = $collection->shareLinks()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CollectionShareLink $link) => [
                'id'               => $link->id,
                'label'            => $link->label,
                'url'              => $link->getUrl(),
                'expires_at'       => $link->expires_at?->toIso8601String(),
                'expired'          => $link->isExpired(),
                'access_count'     => $link->access_count,
                'last_accessed_at' => $link->last_accessed_at?->toIso8601String(),
                'created_at'       => $link->created_at->toIso8601String(),
            ]);

        return $this->success($links);
    }

    public function store(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $validated = $request->validate([
            'label'      => 'nullable|string|max:255',
            'expires_in' => 'nullable|string|in:1h,24h,7d,30d',
        ]);

        $link = $this->service->createLink(
            $collection,
            $request->user(),
            $validated['label'] ?? null,
            $validated['expires_in'] ?? null,
        );

        return $this->success([
            'id'         => $link->id,
            'label'      => $link->label,
            'url'        => $link->getUrl(),
            'token'      => $link->token,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'created_at' => $link->created_at->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, Collection $collection, CollectionShareLink $shareLink): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        if ($shareLink->collection_id !== $collection->id) {
            return $this->error('Share link does not belong to this collection.', 404);
        }

        $this->service->revoke($shareLink);

        return $this->success(['message' => 'Share link revoked.']);
    }
}
