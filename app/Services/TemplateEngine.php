<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\User;

class TemplateEngine
{
    private const PATTERN = '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/';
    private const INCLUDE_PATTERN = '/\{\{>([a-zA-Z0-9_-]+)\}\}/';

    public function extractVariables(string $content): array
    {
        preg_match_all(self::PATTERN, $content, $matches);
        return array_values(array_unique($matches[1]));
    }

    public function extractIncludes(string $content): array
    {
        preg_match_all(self::INCLUDE_PATTERN, $content, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     * Resolve all {{>slug}} includes recursively, then render variables.
     *
     * @return array{rendered: string, variables_used: string[], variables_missing: string[], includes_resolved: string[]}
     */
    /**
     * @param bool $strict When true, throws if required variables are missing (no value provided, no default in metadata)
     */
    public function render(string $content, array $variables, ?array $metadata = null, ?User $user = null, bool $strict = false): array
    {
        $includesResolved = [];
        $resolvedContent = $this->resolveIncludes($content, [], $includesResolved, $user);

        // Merge metadata from included prompts
        $mergedMetadata = $metadata ?? [];
        foreach ($includesResolved as $slug) {
            $included = $this->findPromptBySlug($slug, $user);
            $activeVersion = $included?->active_version;
            if ($activeVersion?->variable_metadata) {
                $mergedMetadata = array_merge($activeVersion->variable_metadata, $mergedMetadata);
            }
        }
        if (empty($mergedMetadata)) {
            $mergedMetadata = null;
        }

        $missing = [];
        $used = [];

        $rendered = preg_replace_callback(self::PATTERN, function ($matches) use ($variables, $mergedMetadata, &$missing, &$used) {
            $name = $matches[1];
            if (array_key_exists($name, $variables)) {
                $used[] = $name;
                return $variables[$name];
            }
            if ($mergedMetadata && isset($mergedMetadata[$name]['default']) && $mergedMetadata[$name]['default'] !== null) {
                $used[] = $name;
                return $mergedMetadata[$name]['default'];
            }
            $missing[] = $name;
            return $matches[0];
        }, $resolvedContent);

        if ($strict && !empty($missing)) {
            $missingList = implode(', ', array_unique($missing));
            throw new \InvalidArgumentException("Missing required variables: {$missingList}. Provide values or set defaults in variable metadata.");
        }

        return [
            'rendered'           => $rendered,
            'variables_used'     => array_values(array_unique($used)),
            'variables_missing'  => array_values(array_unique($missing)),
            'includes_resolved'  => array_values(array_unique($includesResolved)),
        ];
    }

    private function resolveIncludes(string $content, array $chain, array &$resolved, ?User $user = null): string
    {
        $maxDepth = config('urge.max_include_depth', 10);

        return preg_replace_callback(self::INCLUDE_PATTERN, function ($matches) use ($chain, &$resolved, $maxDepth, $user) {
            $slug = $matches[1];

            if (in_array($slug, $chain, true)) {
                $path = implode(' → ', [...$chain, $slug]);
                throw new \RuntimeException("Circular include detected: {$path}");
            }

            if (count($chain) >= $maxDepth) {
                throw new \RuntimeException("Max include depth ({$maxDepth}) exceeded.");
            }

            $prompt = $this->findPromptBySlug($slug, $user);
            $version = $prompt?->active_version;
            if (!$prompt || !$version) {
                return $matches[0];
            }

            $resolved[] = $slug;

            return $this->resolveIncludes($version->content, [...$chain, $slug], $resolved, $user);
        }, $content);
    }

    /**
     * Resolve a prompt by slug, preferring user's own, then visible, then global fallback.
     */
    private function findPromptBySlug(string $slug, ?User $user): ?Prompt
    {
        if ($user) {
            // Try user's own prompt first
            $own = Prompt::where('slug', $slug)->where('created_by', $user->id)->first();
            if ($own) {
                return $own;
            }
            // Then any visible prompt
            return Prompt::visibleTo($user)->where('slug', $slug)->oldest()->first();
        }

        // No user context — global fallback
        return Prompt::where('slug', $slug)->first();
    }
}
