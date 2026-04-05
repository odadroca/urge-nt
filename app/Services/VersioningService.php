<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VersioningService
{
    public function __construct(private TemplateEngine $templateEngine) {}

    public function createVersion(Prompt $prompt, array $data, User $author, ?PromptBranch $branch = null): PromptVersion
    {
        return DB::transaction(function () use ($prompt, $data, $author, $branch) {
            // Resolve branch: provided > prompt default > auto-create "main"
            if (!$branch) {
                $branch = $prompt->defaultBranch;
                if (!$branch) {
                    $branch = $this->ensureDefaultBranch($prompt, $author);
                }
            }

            // Global version number (backwards compat)
            $maxVersion = PromptVersion::where('prompt_id', $prompt->id)->max('version_number');
            $nextNumber = ($maxVersion ?? 0) + 1;

            // Per-branch version number
            $maxBranchVersion = PromptVersion::where('branch_id', $branch->id)->max('branch_version_number');
            $nextBranchNumber = ($maxBranchVersion ?? 0) + 1;

            $variables = $this->templateEngine->extractVariables($data['content']);
            $includes = $this->templateEngine->extractIncludes($data['content']);

            $metadata = $data['variable_metadata'] ?? null;
            if ($metadata) {
                $metadata = array_intersect_key($metadata, array_flip($variables));
                if (empty($metadata)) {
                    $metadata = null;
                }
            }

            $version = PromptVersion::create([
                'prompt_id'              => $prompt->id,
                'branch_id'              => $branch->id,
                'version_number'         => $nextNumber,
                'branch_version_number'  => $nextBranchNumber,
                'content'                => $data['content'],
                'commit_message'         => $data['commit_message'] ?? null,
                'variables'              => $variables,
                'variable_metadata'      => $metadata,
                'includes'               => !empty($includes) ? $includes : null,
                'created_by'             => $author->id,
            ]);

            // Update branch HEAD
            $branch->update(['head_version_id' => $version->id]);

            return $version;
        });
    }

    public function createBranch(Prompt $prompt, string $name, User $author, ?PromptVersion $fromVersion = null): PromptBranch
    {
        return DB::transaction(function () use ($prompt, $name, $author, $fromVersion) {
            return PromptBranch::create([
                'prompt_id'              => $prompt->id,
                'name'                   => $name,
                'head_version_id'        => $fromVersion?->id,
                'forked_from_version_id' => $fromVersion?->id,
                'is_default'             => false,
                'created_by'             => $author->id,
            ]);
        });
    }

    public function deleteBranch(PromptBranch $branch): void
    {
        if ($branch->is_default) {
            throw new \RuntimeException('Cannot delete the default branch.');
        }

        DB::transaction(function () use ($branch) {
            // Orphan versions (raw query to bypass immutability guard)
            DB::table('prompt_versions')
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null, 'branch_version_number' => null]);

            $branch->delete();
        });
    }

    public function setDefaultBranch(Prompt $prompt, PromptBranch $branch): void
    {
        DB::transaction(function () use ($prompt, $branch) {
            $prompt->branches()->update(['is_default' => false]);
            $branch->update(['is_default' => true]);
            $prompt->update(['default_branch_id' => $branch->id]);
        });
    }

    private function ensureDefaultBranch(Prompt $prompt, User $author): PromptBranch
    {
        $branch = $prompt->branches()->where('is_default', true)->first();
        if ($branch) {
            return $branch;
        }

        $branch = PromptBranch::create([
            'prompt_id'  => $prompt->id,
            'name'       => 'main',
            'is_default' => true,
            'created_by' => $author->id,
        ]);

        $prompt->update(['default_branch_id' => $branch->id]);

        return $branch;
    }
}
