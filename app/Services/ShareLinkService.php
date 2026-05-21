<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionShareLink;
use App\Models\User;

class ShareLinkService
{
    public function createLink(
        Collection $collection,
        User $creator,
        ?string $label = null,
        ?string $expiresIn = null,
    ): CollectionShareLink {
        $token = bin2hex(random_bytes(32));

        // Require an explicit expiry. Previously default/unknown → null
        // (link never expires); TPL-06 closes that.
        $expiresAt = match ($expiresIn) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            '30d' => now()->addMonth(),
            default => throw new \InvalidArgumentException(
                'expires_in is required: must be one of 1h, 24h, 7d, 30d.'
            ),
        };

        return CollectionShareLink::create([
            'collection_id' => $collection->id,
            'token' => $token,
            'label' => $label,
            'expires_at' => $expiresAt,
            'created_by' => $creator->id,
        ]);
    }

    public function findByToken(string $token): ?CollectionShareLink
    {
        $link = CollectionShareLink::where('token', $token)
            ->whereHas('collection', fn ($q) => $q->whereNull('deleted_at'))
            ->first();

        if (! $link || ! $link->isValid()) {
            return null;
        }

        return $link;
    }

    public function revoke(CollectionShareLink $link): void
    {
        $link->delete();
    }
}
