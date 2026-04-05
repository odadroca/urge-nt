<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;

class ImportExportService
{
    public function exportPromptVersion(PromptVersion $version): string
    {
        $version->loadMissing('prompt.creator');
        $meta = [
            'prompt' => $version->prompt->slug,
            'owner' => $version->prompt->creator?->slug,
            'version' => $version->version_number,
            'variables' => implode(', ', $version->variables ?? []),
            'includes' => implode(', ', $version->includes ?? []),
        ];

        if ($version->commit_message) {
            $meta['commit_message'] = $version->commit_message;
        }

        $meta['created_at'] = $version->created_at->toIso8601String();

        return $this->buildMarkdown($meta, $version->content);
    }

    public function exportResult(Result $result): string
    {
        $result->loadMissing(['prompt.creator', 'promptVersion']);

        $meta = [
            'prompt' => $result->prompt->slug ?? 'unknown',
            'owner' => $result->prompt->creator?->slug,
            'version' => $result->promptVersion->version_number ?? 0,
            'provider' => $result->provider_name ?? '',
            'model' => $result->model_name ?? '',
            'source' => $result->source,
            'rating' => $result->rating ?? 0,
            'starred' => $result->starred ? 'true' : 'false',
        ];

        if ($result->input_tokens || $result->output_tokens) {
            $meta['input_tokens'] = $result->input_tokens ?? 0;
            $meta['output_tokens'] = $result->output_tokens ?? 0;
        }

        if ($result->duration_ms) {
            $meta['duration_ms'] = $result->duration_ms;
        }

        $meta['created_at'] = $result->created_at->toIso8601String();

        return $this->buildMarkdown($meta, $result->response_text ?? '');
    }

    public function exportCollection(Collection $collection): string
    {
        $collection->loadMissing('items');
        $meta = [
            'collection' => $collection->title,
            'description' => $collection->description ?? '',
            'items_count' => $collection->items->count(),
            'created_at' => $collection->created_at->toIso8601String(),
        ];

        $body = "# {$collection->title}\n\n";
        if ($collection->description) {
            $body .= "{$collection->description}\n\n";
        }

        foreach ($collection->items->sortBy('sort_order') as $item) {
            $resolved = $item->item;
            if (!$resolved) {
                continue;
            }

            if ($item->item_type === 'prompt_version') {
                $resolved->loadMissing('prompt');
                $body .= "## [{$resolved->prompt->name}] v{$resolved->version_number}\n\n";
                $body .= $resolved->content . "\n\n";
            } elseif ($item->item_type === 'result') {
                $provider = $resolved->provider_name ?: 'Manual';
                $body .= "## Result: {$provider}";
                if ($resolved->model_name) {
                    $body .= " ({$resolved->model_name})";
                }
                $body .= "\n\n";
                $body .= $resolved->response_text . "\n\n";
            }

            if ($item->notes) {
                $body .= "> {$item->notes}\n\n";
            }

            $body .= "---\n\n";
        }

        return $this->buildMarkdown($meta, trim($body));
    }

    public function parseMarkdownWithFrontmatter(string $content): array
    {
        $content = ltrim($content);

        if (!str_starts_with($content, '---')) {
            return ['meta' => [], 'body' => $content];
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);

        if (count($parts) < 3) {
            return ['meta' => [], 'body' => $content];
        }

        $yamlBlock = trim($parts[1]);
        $body = trim($parts[2]);

        $meta = [];
        foreach (explode("\n", $yamlBlock) as $line) {
            $line = trim($line);
            if (empty($line) || !str_contains($line, ':')) {
                continue;
            }
            $colonPos = strpos($line, ':');
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            // Remove surrounding quotes
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
                $value = $m[1];
            }

            $meta[$key] = $value;
        }

        return ['meta' => $meta, 'body' => $body];
    }

    public function importResult(string $markdown, PromptVersion $version, User $user, ?string $filename = null): Result
    {
        $parsed = $this->parseMarkdownWithFrontmatter($markdown);
        $meta = $parsed['meta'];

        return Result::create([
            'prompt_id' => $version->prompt_id,
            'prompt_version_id' => $version->id,
            'source' => 'import',
            'provider_name' => $meta['provider'] ?? null,
            'model_name' => $meta['model'] ?? null,
            'response_text' => $parsed['body'],
            'rating' => isset($meta['rating']) ? (int) $meta['rating'] : null,
            'starred' => isset($meta['starred']) && $meta['starred'] === 'true',
            'input_tokens' => isset($meta['input_tokens']) ? (int) $meta['input_tokens'] : null,
            'output_tokens' => isset($meta['output_tokens']) ? (int) $meta['output_tokens'] : null,
            'duration_ms' => isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : null,
            'notes' => $meta['notes'] ?? null,
            'status' => 'success',
            'import_filename' => $filename,
            'created_by' => $user->id,
        ]);
    }

    private function buildMarkdown(array $meta, string $body): string
    {
        $frontmatter = "---\n";
        foreach ($meta as $key => $value) {
            if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }
            $frontmatter .= "{$key}: {$value}\n";
        }
        $frontmatter .= "---\n\n";

        return $frontmatter . $body . "\n";
    }
}
