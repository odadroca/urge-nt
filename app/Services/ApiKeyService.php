<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyService
{
    public function generateKey(User $user, string $name, array $promptIds = []): array
    {
        $prefix = config('urge.key_prefix', 'urge_');
        $bytes = config('urge.key_bytes', 31);
        $previewLength = config('urge.key_preview_length', 8);

        $raw = $prefix . rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
        $hash = hash('sha256', $raw);
        $preview = substr($raw, 0, $previewLength);

        $apiKey = ApiKey::create([
            'name'        => $name,
            'user_id'     => $user->id,
            'key_hash'    => $hash,
            'key_preview' => $preview,
        ]);

        if (!empty($promptIds)) {
            $apiKey->prompts()->sync($promptIds);
        }

        return ['key' => $raw, 'model' => $apiKey];
    }

    public function findByToken(string $token): ?ApiKey
    {
        $hash = hash('sha256', $token);

        $apiKey = ApiKey::where('key_hash', $hash)->first();

        if (!$apiKey || !$apiKey->isValid()) {
            return null;
        }

        return $apiKey;
    }
}
