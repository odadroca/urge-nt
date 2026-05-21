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
     * Security posture (PB-3):
     *  - `$user` MUST be supplied. With $user=null, includes resolve to
     *    nothing (previously they fell back to a global slug lookup —
     *    TPL-01 / TPL-02). Callers like PipelineService now thread the
     *    invoking user through; the public share page renders as the
     *    collection owner.
     *  - Caller-supplied metadata is the ONLY source of variable defaults
     *    (TPL-05). Included-fragment metadata is no longer merged into
     *    defaults — a malicious shared fragment cannot inject a default
     *    for a parent-scope variable.
     *  - Variable values must be scalar (TPL-03). Non-scalar values raise
     *    InvalidArgumentException to avoid downstream TypeError 500s.
     *  - Global expansion-count + total-output-size budget guard against
     *    sibling-fanout amplification (TPL-04 — "billion laughs").
     *
     * @param  array  $variables  name => scalar value
     * @param  ?array  $metadata  caller-supplied metadata (variable defaults)
     * @param  ?User  $user  invoking user for visibility scoping
     * @param  bool  $strict  throw if required vars are missing
     * @param  bool  $strictIncludes  throw if an include cannot be resolved
     * @return array{rendered: string, variables_used: string[], variables_missing: string[], includes_resolved: string[]}
     */
    public function render(
        string $content,
        array $variables,
        ?array $metadata = null,
        ?User $user = null,
        bool $strict = false,
        bool $strictIncludes = false,
    ): array {
        $this->validateVariableValues($variables);

        $state = [
            'expansions' => 0,
            'bytes' => 0,
            'max_expansions' => (int) config('urge.max_include_expansions', 500),
            'max_render_bytes' => (int) config('urge.max_render_bytes', 5 * 1024 * 1024),
        ];

        $includesResolved = [];
        $resolvedContent = $this->resolveIncludes($content, [], $includesResolved, $user, $strictIncludes, $state);

        $missing = [];
        $used = [];

        $rendered = preg_replace_callback(self::PATTERN, function ($matches) use ($variables, $metadata, &$missing, &$used) {
            $name = $matches[1];
            if (array_key_exists($name, $variables) && $variables[$name] !== null) {
                $used[] = $name;

                return (string) $variables[$name];
            }
            if ($metadata && isset($metadata[$name]['default']) && $metadata[$name]['default'] !== null) {
                $used[] = $name;

                return (string) $metadata[$name]['default'];
            }
            $missing[] = $name;

            return $matches[0];
        }, $resolvedContent);

        if ($strict && ! empty($missing)) {
            $missingList = implode(', ', array_unique($missing));
            throw new \InvalidArgumentException(
                "Missing required variables: {$missingList}. Provide values or set defaults in variable metadata."
            );
        }

        // Final size guard — variable expansion could (in theory) inflate
        // output past the include-time check.
        if (strlen($rendered) > $state['max_render_bytes']) {
            throw new \RuntimeException(
                "Rendered output size exceeded max ({$state['max_render_bytes']} bytes)."
            );
        }

        return [
            'rendered' => $rendered,
            'variables_used' => array_values(array_unique($used)),
            'variables_missing' => array_values(array_unique($missing)),
            'includes_resolved' => array_values(array_unique($includesResolved)),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function validateVariableValues(array $variables): void
    {
        foreach ($variables as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (! is_scalar($value)) {
                throw new \InvalidArgumentException(
                    "Variable '{$name}' must be a scalar value (string, number, or boolean)."
                );
            }
        }
    }

    /**
     * @param  array<string>  $chain
     * @param  array<string>  $resolved
     * @param  array{expansions:int, bytes:int, max_expansions:int, max_render_bytes:int}  $state
     */
    private function resolveIncludes(
        string $content,
        array $chain,
        array &$resolved,
        ?User $user,
        bool $strictIncludes,
        array &$state,
    ): string {
        $maxDepth = (int) config('urge.max_include_depth', 10);

        return preg_replace_callback(self::INCLUDE_PATTERN, function ($matches) use ($chain, &$resolved, $maxDepth, $user, $strictIncludes, &$state) {
            $slug = $matches[1];

            if (in_array($slug, $chain, true)) {
                $path = implode(' → ', [...$chain, $slug]);
                throw new \RuntimeException("Circular include detected: {$path}");
            }

            if (count($chain) >= $maxDepth) {
                throw new \RuntimeException("Max include depth ({$maxDepth}) exceeded.");
            }

            if ($state['expansions'] >= $state['max_expansions']) {
                throw new \RuntimeException(
                    "Include expansion budget exceeded ({$state['max_expansions']})."
                );
            }

            $prompt = $this->findPromptBySlug($slug, $user);
            $version = $prompt?->active_version;
            if (! $prompt || ! $version) {
                if ($strictIncludes) {
                    throw new \RuntimeException("Include not available: {$slug}");
                }

                return $matches[0];
            }

            $state['expansions']++;
            $resolved[] = $slug;

            $expanded = $this->resolveIncludes(
                $version->content,
                [...$chain, $slug],
                $resolved,
                $user,
                $strictIncludes,
                $state,
            );

            $state['bytes'] += strlen($expanded);
            if ($state['bytes'] > $state['max_render_bytes']) {
                throw new \RuntimeException(
                    "Rendered output size exceeded max ({$state['max_render_bytes']} bytes)."
                );
            }

            return $expanded;
        }, $content);
    }

    /**
     * Resolve a prompt by slug, strictly scoped to the given user's visibility.
     *
     * PB-3: the pre-PB-3 global-slug fallback for null users has been
     * removed (TPL-01, TPL-02). With no user context, returns null —
     * callers fail closed.
     */
    private function findPromptBySlug(string $slug, ?User $user): ?Prompt
    {
        if (! $user) {
            return null;
        }

        // Owner's own prompt wins on tie
        $own = Prompt::where('slug', $slug)->where('created_by', $user->id)->first();
        if ($own) {
            return $own;
        }

        return Prompt::visibleTo($user)->where('slug', $slug)->oldest()->first();
    }
}
