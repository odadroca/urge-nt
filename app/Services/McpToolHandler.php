<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Models\PipelineTemplate;
use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\Team;
use App\Models\User;

class McpToolHandler
{
    public function __construct(
        private TemplateEngine $templateEngine,
        private VersioningService $versioningService,
        private PipelineTemplateService $pipelineService,
    ) {}

    public function getServerInfo(): array
    {
        return [
            'name'    => 'urge',
            'version' => '2.0.0',
        ];
    }

    public function getRequiredScope(string $toolName): ?string
    {
        $readTools = [
            'get_prompt', 'list_prompts', 'render_prompt',
            'get_results', 'list_branches', 'list_teams', 'list_templates',
            'list_providers',
        ];

        $writeTools = [
            'create_prompt', 'save_version', 'store_result', 'update_result',
            'create_branch', 'share_prompt', 'run_template', 'run_prompt',
        ];

        $adminTools = [
            'delete_prompt', 'delete_result',
        ];

        if (in_array($toolName, $readTools)) {
            return 'mcp:read';
        }
        if (in_array($toolName, $writeTools)) {
            return 'mcp:write';
        }
        if (in_array($toolName, $adminTools)) {
            return 'mcp:admin';
        }

        return null;
    }

    public function getToolDefinitions(): array
    {
        return [
            [
                'name'        => 'create_prompt',
                'description' => 'Create a new prompt or fragment. Returns the created prompt with its slug.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'        => ['type' => 'string', 'description' => 'The prompt name'],
                        'type'        => ['type' => 'string', 'enum' => ['prompt', 'fragment'], 'description' => 'Type (default: prompt)'],
                        'description' => ['type' => 'string', 'description' => 'Optional description'],
                        'content'     => ['type' => 'string', 'description' => 'Optional initial content (creates first version)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name'        => 'run_prompt',
                'description' => 'Run a prompt through a registered LLM provider. Renders the template with variables, dispatches to the provider, stores the result, and returns the LLM response.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'      => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'     => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'provider'  => ['type' => 'string', 'description' => 'LLM provider name (e.g. "OpenAI", "Mistral"). Use list_providers to see available providers.'],
                        'version'   => ['type' => 'integer', 'description' => 'Version number (defaults to active version)'],
                        'variables' => ['type' => 'object', 'description' => 'Key-value pairs for template variables'],
                        'branch'    => ['type' => 'string', 'description' => 'Branch name (defaults to default branch)'],
                    ],
                    'required' => ['slug', 'provider'],
                ],
            ],
            [
                'name'        => 'list_providers',
                'description' => 'List active LLM providers configured in URGE. Use these names with run_prompt.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'get_prompt',
                'description' => 'Get a prompt by slug with its active version content and metadata.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'    => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'   => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'version' => ['type' => 'integer', 'description' => 'Optional version number (defaults to active version)'],
                        'branch'  => ['type' => 'string', 'description' => 'Branch name (defaults to default branch)'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'list_prompts',
                'description' => 'List available prompts with optional filters.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'type'     => ['type' => 'string', 'enum' => ['prompt', 'fragment'], 'description' => 'Filter by type'],
                        'category' => ['type' => 'string', 'description' => 'Filter by category name'],
                        'tag'      => ['type' => 'string', 'description' => 'Filter by tag'],
                        'search'   => ['type' => 'string', 'description' => 'Search name and description'],
                        'scope'    => ['type' => 'string', 'description' => "Scope: 'mine' (default), 'shared', 'team:{slug}', or 'all'"],
                    ],
                ],
            ],
            [
                'name'        => 'render_prompt',
                'description' => 'Render a prompt template with variable substitution and include resolution.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'      => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'     => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'version'   => ['type' => 'integer', 'description' => 'Optional version number'],
                        'variables' => ['type' => 'object', 'description' => 'Key-value pairs for template variables'],
                        'branch'    => ['type' => 'string', 'description' => 'Branch name (defaults to default branch)'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'save_version',
                'description' => 'Create a new version of a prompt.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'           => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'          => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'content'        => ['type' => 'string', 'description' => 'The prompt content'],
                        'commit_message' => ['type' => 'string', 'description' => 'Optional commit message'],
                        'branch'         => ['type' => 'string', 'description' => 'Branch name (defaults to default branch)'],
                    ],
                    'required' => ['slug', 'content'],
                ],
            ],
            [
                'name'        => 'store_result',
                'description' => 'Store an LLM response result for a prompt version.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'          => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'         => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'version'       => ['type' => 'integer', 'description' => 'The version number'],
                        'response_text' => ['type' => 'string', 'description' => 'The LLM response text'],
                        'provider'      => ['type' => 'string', 'description' => 'Provider name (e.g. OpenAI)'],
                        'model'         => ['type' => 'string', 'description' => 'Model name (e.g. gpt-4)'],
                        'notes'         => ['type' => 'string', 'description' => 'Optional notes'],
                        'branch'        => ['type' => 'string', 'description' => 'Branch name to scope version lookup'],
                    ],
                    'required' => ['slug', 'version', 'response_text'],
                ],
            ],
            [
                'name'        => 'delete_prompt',
                'description' => 'Delete a prompt by slug (soft delete). Only the prompt owner or an admin can delete.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'  => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner' => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'get_results',
                'description' => 'Get results for a prompt, optionally filtered by version or starred status.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'    => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'   => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'version' => ['type' => 'integer', 'description' => 'Filter by version number'],
                        'starred' => ['type' => 'boolean', 'description' => 'Filter by starred status'],
                        'limit'   => ['type' => 'integer', 'description' => 'Max results (default 10)'],
                        'branch'  => ['type' => 'string', 'description' => 'Branch name to scope version lookup'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'list_branches',
                'description' => 'List branches for a prompt with version counts and HEAD info.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'  => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner' => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'create_branch',
                'description' => 'Create a new branch for a prompt, optionally forking from an existing version.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'         => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'        => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'name'         => ['type' => 'string', 'description' => 'Branch name (will be slugified)'],
                        'from_version' => ['type' => 'integer', 'description' => 'Version number to fork from (optional)'],
                    ],
                    'required' => ['slug', 'name'],
                ],
            ],
            [
                'name'        => 'update_result',
                'description' => 'Update a result (rating, starred status, notes).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'      => ['type' => 'integer', 'description' => 'The result ID'],
                        'rating'  => ['type' => 'integer', 'description' => 'Rating 1-5'],
                        'starred' => ['type' => 'boolean', 'description' => 'Starred status'],
                        'notes'   => ['type' => 'string', 'description' => 'Notes'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name'        => 'delete_result',
                'description' => 'Permanently delete a result.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'The result ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name'        => 'share_prompt',
                'description' => 'Share a prompt you own with a team. Sets the prompt visibility to shared.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'      => ['type' => 'string', 'description' => 'The prompt slug'],
                        'team_slug' => ['type' => 'string', 'description' => 'The team slug to share with'],
                    ],
                    'required' => ['slug', 'team_slug'],
                ],
            ],
            [
                'name'        => 'list_teams',
                'description' => 'List teams you belong to with member and prompt counts.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'list_templates',
                'description' => 'List active pipeline templates with their channel counts.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'run_template',
                'description' => 'Run a pipeline template against a prompt version. Dispatches parallel LLM calls per channel and optional synthesis.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'          => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'         => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'template_slug' => ['type' => 'string', 'description' => 'Pipeline template slug'],
                        'version'       => ['type' => 'integer', 'description' => 'Optional version number (defaults to active version)'],
                        'variables'     => ['type' => 'object', 'description' => 'Key-value pairs for template variables'],
                    ],
                    'required' => ['slug', 'template_slug'],
                ],
            ],
        ];
    }

    public function getResourceDefinitions(): array
    {
        return [
            [
                'uri'         => 'urge://prompts',
                'name'        => 'Visible Prompts',
                'description' => 'List of prompts visible to the current user as JSON',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'urge://prompts/{owner}/{slug}',
                'name'        => 'Prompt Content (namespaced)',
                'description' => 'Active version content of a prompt by owner/slug',
                'mimeType'    => 'text/plain',
            ],
            [
                'uri'         => 'urge://prompts/{owner}/{slug}/v/{n}',
                'name'        => 'Prompt Version Content (namespaced)',
                'description' => 'Specific version content of a prompt by owner/slug',
                'mimeType'    => 'text/plain',
            ],
            [
                'uri'         => 'urge://prompts/{owner}/{slug}/branches',
                'name'        => 'Prompt Branches',
                'description' => 'List branches for a prompt by owner/slug',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'urge://prompts/{owner}/{slug}/branches/{branch}',
                'name'        => 'Branch HEAD Content',
                'description' => 'HEAD version content of a specific branch',
                'mimeType'    => 'text/plain',
            ],
            [
                'uri'         => 'urge://teams',
                'name'        => 'My Teams',
                'description' => 'List of teams the current user belongs to as JSON',
                'mimeType'    => 'application/json',
            ],
        ];
    }

    // --- Tool Implementations ---

    public function callTool(string $name, array $arguments, ?User $user = null): array
    {
        return match ($name) {
            'create_prompt'  => $this->createPrompt($arguments, $user),
            'run_prompt'     => $this->runPrompt($arguments, $user),
            'list_providers' => $this->listProviders($arguments, $user),
            'get_prompt'     => $this->getPrompt($arguments, $user),
            'list_prompts'  => $this->listPrompts($arguments, $user),
            'render_prompt' => $this->renderPrompt($arguments, $user),
            'save_version'  => $this->saveVersion($arguments, $user),
            'store_result'  => $this->storeResult($arguments, $user),
            'delete_prompt' => $this->deletePrompt($arguments, $user),
            'get_results'   => $this->getResults($arguments, $user),
            'update_result' => $this->updateResult($arguments, $user),
            'delete_result' => $this->deleteResult($arguments, $user),
            'share_prompt'   => $this->sharePrompt($arguments, $user),
            'list_teams'     => $this->listTeams($arguments, $user),
            'list_branches'  => $this->listBranches($arguments, $user),
            'create_branch'  => $this->createBranch($arguments, $user),
            'list_templates' => $this->listTemplates($arguments, $user),
            'run_template'   => $this->runTemplate($arguments, $user),
            default          => ['error' => "Unknown tool: {$name}"],
        };
    }

    public function readResource(string $uri, ?User $user = null): array
    {
        // urge://prompts (list)
        if ($uri === 'urge://prompts') {
            $query = Prompt::with(['latestVersion', 'creator']);
            if ($user) {
                $query->visibleTo($user);
            }

            $prompts = $query->get()->map(fn ($p) => [
                'slug'          => $p->slug,
                'name'          => $p->name,
                'type'          => $p->type,
                'description'   => $p->description,
                'owner'         => $p->creator?->slug,
                'version_count' => $p->versions()->count(),
            ]);

            return [
                'uri'      => $uri,
                'mimeType' => 'application/json',
                'text'     => json_encode($prompts, JSON_PRETTY_PRINT),
            ];
        }

        // urge://teams
        if ($uri === 'urge://teams') {
            if (!$user) {
                return ['error' => 'User context required for teams resource.'];
            }
            $teams = $user->teams()->withCount(['members', 'prompts'])->get()->map(fn ($t) => [
                'slug'         => $t->slug,
                'name'         => $t->name,
                'role'         => $t->pivot->role,
                'member_count' => $t->members_count,
                'prompt_count' => $t->prompts_count,
            ]);

            return [
                'uri'      => $uri,
                'mimeType' => 'application/json',
                'text'     => json_encode($teams, JSON_PRETTY_PRINT),
            ];
        }

        // urge://prompts/{owner}/{slug}/branches (list branches)
        if (preg_match('#^urge://prompts/([^/]+)/([^/]+)/branches$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[2], $m[1], $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }
            $branches = $prompt->branches()->withCount('versions')->get()->map(fn ($b) => [
                'name'           => $b->name,
                'is_default'     => $b->is_default,
                'versions_count' => $b->versions_count,
                'head_version'   => $b->headVersion?->version_number,
            ]);

            return [
                'uri'      => $uri,
                'mimeType' => 'application/json',
                'text'     => json_encode($branches, JSON_PRETTY_PRINT),
            ];
        }

        // urge://prompts/{owner}/{slug}/branches/{branch} (branch HEAD content)
        if (preg_match('#^urge://prompts/([^/]+)/([^/]+)/branches/([^/]+)$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[2], $m[1], $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }
            $branch = $prompt->branches()->where('name', $m[3])->first();
            if (!$branch) {
                return ['error' => 'Branch not found.'];
            }

            return [
                'uri'      => $uri,
                'mimeType' => 'text/plain',
                'text'     => $branch->headVersion?->content ?? '',
            ];
        }

        // urge://prompts/{owner}/{slug}/v/{n} (namespaced version — 4 segments after prompts/)
        if (preg_match('#^urge://prompts/([^/]+)/([^/]+)/v/(\d+)$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[2], $m[1], $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }
            $version = $prompt->versions()->where('version_number', (int) $m[3])->first();
            if (!$version) {
                return ['error' => 'Version not found.'];
            }

            return [
                'uri'      => $uri,
                'mimeType' => 'text/plain',
                'text'     => $version->content,
            ];
        }

        // urge://prompts/{owner}/{slug} (namespaced — 2 segments after prompts/)
        if (preg_match('#^urge://prompts/([^/]+)/([^/]+)$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[2], $m[1], $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }

            return [
                'uri'      => $uri,
                'mimeType' => 'text/plain',
                'text'     => $prompt->active_version?->content ?? '',
            ];
        }

        // Legacy fallback: urge://prompts/{slug}/v/{n}
        if (preg_match('#^urge://prompts/([^/]+)/v/(\d+)$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[1], null, $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }
            $version = $prompt->versions()->where('version_number', (int) $m[2])->first();
            if (!$version) {
                return ['error' => 'Version not found.'];
            }

            return [
                'uri'      => $uri,
                'mimeType' => 'text/plain',
                'text'     => $version->content,
            ];
        }

        // Legacy fallback: urge://prompts/{slug}
        if (preg_match('#^urge://prompts/([^/]+)$#', $uri, $m)) {
            $prompt = $this->resolvePrompt($m[1], null, $user);
            if (!$prompt) {
                return ['error' => 'Prompt not found or not visible.'];
            }

            return [
                'uri'      => $uri,
                'mimeType' => 'text/plain',
                'text'     => $prompt->active_version?->content ?? '',
            ];
        }

        return ['error' => "Unknown resource: {$uri}"];
    }

    // --- Prompt Resolution ---

    /**
     * Resolve a prompt by slug with optional owner namespace.
     *
     * Resolution order:
     * 1. If $owner provided: find user by slug, then prompt by (created_by, slug)
     * 2. If $owner omitted + $user exists: try user's own prompts first, then all visible
     * 3. If no user context: global slug lookup (backward compat)
     */
    private function resolvePrompt(string $slug, ?string $owner, ?User $user): ?Prompt
    {
        if ($owner) {
            $ownerUser = User::where('slug', $owner)->first();
            if (!$ownerUser) {
                return null;
            }

            $prompt = Prompt::where('created_by', $ownerUser->id)->where('slug', $slug)->first();
            if (!$prompt) {
                return null;
            }

            // Owner always sees their own prompt
            if ($user && $prompt->created_by === $user->id) {
                return $prompt;
            }

            // Check visibility for non-owners
            if ($user) {
                $canSee = Prompt::visibleTo($user)->where('id', $prompt->id)->exists();
                return $canSee ? $prompt : null;
            }

            return $prompt;
        }

        if ($user) {
            // Try current user's own prompts first
            $prompt = Prompt::where('created_by', $user->id)->where('slug', $slug)->first();
            if ($prompt) {
                return $prompt;
            }

            // Fallback: search all visible prompts (oldest first for determinism)
            return Prompt::visibleTo($user)->where('slug', $slug)->orderBy('created_at', 'asc')->first();
        }

        // No user context: global slug lookup
        return Prompt::where('slug', $slug)->first();
    }

    private function verifyOwnership(Prompt $prompt, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $prompt->created_by === $user->id || $user->isAdmin();
    }

    // --- Tool Methods ---

    private function createPrompt(array $args, ?User $user = null): array
    {
        if (!$user) {
            return ['error' => 'Authentication required to create prompts.'];
        }

        $name = $args['name'] ?? '';
        if (!$name) {
            return ['error' => 'name is required.'];
        }

        $prompt = Prompt::create([
            'name'        => $name,
            'type'        => $args['type'] ?? 'prompt',
            'description' => $args['description'] ?? null,
            'created_by'  => $user->id,
        ]);

        $prompt->load('creator');

        $result = [
            'id'          => $prompt->id,
            'name'        => $prompt->name,
            'slug'        => $prompt->slug,
            'type'        => $prompt->type,
            'description' => $prompt->description,
            'owner'       => $prompt->creator?->username ?? $prompt->creator?->name,
        ];

        // Optionally create first version with content
        if (!empty($args['content'])) {
            $version = $this->versioningService->createVersion($prompt, [
                'content'        => $args['content'],
                'commit_message' => 'Initial version',
            ], $user);

            $result['version'] = [
                'version_number' => $version->version_number,
                'content'        => $version->content,
            ];
        }

        return $result;
    }

    private function runPrompt(array $args, ?User $user = null): array
    {
        if (!$user) {
            return ['error' => 'Authentication required to run prompts.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        // Resolve version
        $version = null;
        if (isset($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
        } elseif (isset($args['branch'])) {
            $branch = $prompt->branches()->where('name', $args['branch'])->first();
            if ($branch && $branch->head_version_id) {
                $version = PromptVersion::find($branch->head_version_id);
            }
        }
        $version = $version ?? $prompt->activeVersion;

        if (!$version) {
            return ['error' => 'No version found. Save a version first.'];
        }

        // Find provider
        $providerName = $args['provider'] ?? '';
        $provider = LlmProvider::where('name', 'like', $providerName)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            $available = LlmProvider::where('is_active', true)->pluck('name')->implode(', ');
            return ['error' => "Provider '{$providerName}' not found or inactive. Available: {$available}"];
        }

        // Render template with variables
        $variables = $args['variables'] ?? [];
        $renderResult = $this->templateEngine->render(
            $version->content,
            $variables,
            $version->variable_metadata,
            $user,
        );
        $renderedContent = $renderResult['rendered'];

        // Dispatch to LLM
        $dispatchService = app(LlmDispatchService::class);
        $llmResult = $dispatchService->dispatch($provider, $renderedContent);

        // Store result
        $result = Result::create([
            'prompt_id'          => $prompt->id,
            'prompt_version_id'  => $version->id,
            'source'             => 'mcp',
            'provider_name'      => $provider->name,
            'model_name'         => $llmResult->modelUsed,
            'llm_provider_id'    => $provider->id,
            'rendered_content'   => $renderedContent,
            'variables_used'     => !empty($variables) ? $variables : null,
            'response_text'      => $llmResult->success ? $llmResult->text : null,
            'input_tokens'       => $llmResult->inputTokens,
            'output_tokens'      => $llmResult->outputTokens,
            'duration_ms'        => $llmResult->durationMs,
            'status'             => $llmResult->success ? 'success' : 'error',
            'error_message'      => $llmResult->error,
            'created_by'         => $user->id,
        ]);

        if (!$llmResult->success) {
            return ['error' => "LLM call failed: {$llmResult->error}"];
        }

        return [
            'response_text'  => $llmResult->text,
            'provider'       => $provider->name,
            'model'          => $llmResult->modelUsed,
            'input_tokens'   => $llmResult->inputTokens,
            'output_tokens'  => $llmResult->outputTokens,
            'duration_ms'    => $llmResult->durationMs,
            'result_id'      => $result->id,
            'prompt_slug'    => $prompt->slug,
            'version_number' => $version->version_number,
        ];
    }

    private function listProviders(array $args, ?User $user = null): array
    {
        $providers = LlmProvider::where('is_active', true)
            ->get(['id', 'name', 'driver', 'model'])
            ->map(fn ($p) => [
                'name'   => $p->name,
                'driver' => $p->driver,
                'model'  => $p->model,
            ])
            ->values()
            ->toArray();

        return ['providers' => $providers];
    }

    private function getPrompt(array $args, ?User $user = null): array
    {
        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        if (!empty($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
        } elseif (!empty($args['branch'])) {
            $branch = $prompt->branches()->where('name', $args['branch'])->first();
            if (!$branch) {
                return ['error' => 'Branch not found.'];
            }
            $version = $branch->headVersion;
        } else {
            $version = $prompt->active_version;
        }

        if (!$version) {
            return ['error' => 'Version not found.'];
        }

        return [
            'slug'                  => $prompt->slug,
            'name'                  => $prompt->name,
            'type'                  => $prompt->type,
            'description'           => $prompt->description,
            'owner'                 => $prompt->creator?->slug,
            'version_number'        => $version->version_number,
            'branch'                => $version->branch?->name,
            'branch_version_number' => $version->branch_version_number,
            'content'               => $version->content,
            'variables'             => $version->variables ?? [],
            'variable_metadata'     => $version->variable_metadata,
            'includes'              => $version->includes ?? [],
            'commit_message'        => $version->commit_message,
        ];
    }

    private function listPrompts(array $args, ?User $user = null): array
    {
        $query = Prompt::query()->with('creator');

        // Apply visibility scoping
        if ($user) {
            $scope = $args['scope'] ?? 'mine';

            if ($scope === 'mine') {
                $query->where('created_by', $user->id);
            } elseif ($scope === 'shared') {
                $query->visibleTo($user)->where('created_by', '!=', $user->id);
            } elseif ($scope === 'all') {
                $query->visibleTo($user);
            } elseif (str_starts_with($scope, 'team:')) {
                $teamSlug = substr($scope, 5);
                $query->visibleTo($user)->whereHas('teams', fn ($q) => $q->where('slug', $teamSlug));
            } else {
                $query->where('created_by', $user->id); // fallback to 'mine'
            }
        }

        if (!empty($args['type'])) {
            $query->where('type', $args['type']);
        }
        if (!empty($args['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$args['category']}%"));
        }
        if (!empty($args['tag'])) {
            $query->whereJsonContains('tags', $args['tag']);
        }
        if (!empty($args['search'])) {
            $query->where(function ($q) use ($args) {
                $q->where('name', 'like', "%{$args['search']}%")
                  ->orWhere('description', 'like', "%{$args['search']}%");
            });
        }

        return $query->get()->map(fn ($p) => [
            'slug'          => $p->slug,
            'name'          => $p->name,
            'type'          => $p->type,
            'owner'         => $p->creator?->slug,
            'version_count' => $p->versions()->count(),
        ])->toArray();
    }

    private function renderPrompt(array $args, ?User $user = null): array
    {
        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        if (!empty($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
        } elseif (!empty($args['branch'])) {
            $branch = $prompt->branches()->where('name', $args['branch'])->first();
            if (!$branch) {
                return ['error' => 'Branch not found.'];
            }
            $version = $branch->headVersion;
        } else {
            $version = $prompt->active_version;
        }

        if (!$version) {
            return ['error' => 'Version not found.'];
        }

        $variables = $args['variables'] ?? [];

        return $this->templateEngine->render($version->content, $variables, $version->variable_metadata, $user);
    }

    private function saveVersion(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for saving versions.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $branch = null;
        if (!empty($args['branch'])) {
            $branch = $prompt->branches()->where('name', $args['branch'])->first();
            if (!$branch) {
                return ['error' => 'Branch not found.'];
            }
        }

        $version = $this->versioningService->createVersion($prompt, [
            'content'        => $args['content'],
            'commit_message' => $args['commit_message'] ?? null,
        ], $user, $branch);

        return [
            'version_number'        => $version->version_number,
            'branch'                => $version->branch?->name,
            'branch_version_number' => $version->branch_version_number,
            'variables'             => $version->variables ?? [],
            'includes'              => $version->includes ?? [],
        ];
    }

    private function storeResult(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for storing results.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $version = $prompt->versions()->where('version_number', $args['version'])->first();
        if (!$version) {
            return ['error' => 'Version not found.'];
        }

        $result = Result::create([
            'prompt_id'         => $prompt->id,
            'prompt_version_id' => $version->id,
            'source'            => 'mcp',
            'response_text'     => $args['response_text'],
            'provider_name'     => $args['provider'] ?? null,
            'model_name'        => $args['model'] ?? null,
            'notes'             => $args['notes'] ?? null,
            'created_by'        => $user->id,
        ]);

        return ['id' => $result->id, 'created' => true];
    }

    private function deletePrompt(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for deleting prompts.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        if (!$this->verifyOwnership($prompt, $user)) {
            return ['error' => 'Only the prompt owner or an admin can delete this prompt.'];
        }

        $prompt->delete();

        return ['deleted' => true, 'slug' => $prompt->slug];
    }

    private function getResults(array $args, ?User $user = null): array
    {
        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $query = $prompt->results();

        if (!empty($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
            if ($version) {
                $query->where('prompt_version_id', $version->id);
            }
        }

        if (isset($args['starred'])) {
            $query->where('starred', $args['starred']);
        }

        $limit = min($args['limit'] ?? 10, 50);

        return $query->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id'            => $r->id,
                'version'       => $r->promptVersion?->version_number,
                'provider'      => $r->provider_name,
                'model'         => $r->model_name,
                'response_text' => $r->response_text,
                'rating'        => $r->rating,
                'starred'       => $r->starred,
                'notes'         => $r->notes,
                'created_at'    => $r->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    private function updateResult(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for updating results.'];
        }

        $result = Result::find($args['id'] ?? 0);
        if (!$result) {
            return ['error' => 'Result not found.'];
        }

        // Verify user can see the parent prompt
        if ($result->prompt_id) {
            $canSee = Prompt::visibleTo($user)->where('id', $result->prompt_id)->exists();
            if (!$canSee) {
                return ['error' => 'Result not found.'];
            }
        }

        $updates = [];
        if (isset($args['rating'])) {
            $rating = (int) $args['rating'];
            if ($rating < 1 || $rating > 5) {
                return ['error' => 'Rating must be between 1 and 5.'];
            }
            $updates['rating'] = $rating;
        }
        if (isset($args['starred'])) {
            $updates['starred'] = (bool) $args['starred'];
        }
        if (isset($args['notes'])) {
            $updates['notes'] = $args['notes'];
        }

        if (empty($updates)) {
            return ['error' => 'No fields to update.'];
        }

        $result->update($updates);

        return [
            'id'      => $result->id,
            'updated' => true,
        ];
    }

    private function deleteResult(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for deleting results.'];
        }

        $result = Result::find($args['id'] ?? 0);
        if (!$result) {
            return ['error' => 'Result not found.'];
        }

        // Verify user can see the parent prompt
        if ($result->prompt_id) {
            $canSee = Prompt::visibleTo($user)->where('id', $result->prompt_id)->exists();
            if (!$canSee) {
                return ['error' => 'Result not found.'];
            }
        }

        $result->delete();

        return ['id' => $result->id, 'deleted' => true];
    }

    private function sharePrompt(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for sharing prompts.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        if (!$this->verifyOwnership($prompt, $user)) {
            return ['error' => 'Only the prompt owner can share this prompt.'];
        }

        $team = Team::where('slug', $args['team_slug'] ?? '')->first();
        if (!$team) {
            return ['error' => 'Team not found.'];
        }

        // Verify user belongs to the team (unless admin)
        if (!$team->members()->where('users.id', $user->id)->exists() && !$user->isAdmin()) {
            return ['error' => 'You are not a member of this team.'];
        }

        if (!$prompt->teams()->where('teams.id', $team->id)->exists()) {
            $prompt->teams()->attach($team->id);
        }

        $prompt->update(['visibility' => 'shared']);

        return [
            'shared' => true,
            'slug'   => $prompt->slug,
            'team'   => $team->slug,
        ];
    }

    private function listTeams(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for listing teams.'];
        }

        return $user->teams()->withCount(['members', 'prompts'])->get()->map(fn ($t) => [
            'slug'         => $t->slug,
            'name'         => $t->name,
            'role'         => $t->pivot->role,
            'member_count' => $t->members_count,
            'prompt_count' => $t->prompts_count,
        ])->toArray();
    }

    private function listBranches(array $args, ?User $user): array
    {
        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        return $prompt->branches()
            ->withCount('versions')
            ->get()
            ->map(fn ($b) => [
                'name'                  => $b->name,
                'is_default'            => $b->is_default,
                'versions_count'        => $b->versions_count,
                'head_version_number'   => $b->headVersion?->version_number,
                'forked_from_version'   => $b->forkedFromVersion?->version_number,
            ])
            ->toArray();
    }

    private function createBranch(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for creating branches.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $fromVersion = null;
        if (!empty($args['from_version'])) {
            $fromVersion = $prompt->versions()->where('version_number', $args['from_version'])->first();
            if (!$fromVersion) {
                return ['error' => 'Source version not found.'];
            }
        }

        $branch = $this->versioningService->createBranch(
            $prompt,
            $args['name'],
            $user,
            $fromVersion
        );

        return [
            'name'                => $branch->name,
            'is_default'          => $branch->is_default,
            'head_version_number' => $branch->headVersion?->version_number,
            'forked_from_version' => $fromVersion?->version_number,
        ];
    }

    private function listTemplates(array $args, ?User $user): array
    {
        return PipelineTemplate::where('is_active', true)
            ->withCount('channels')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'slug'           => $t->slug,
                'name'           => $t->name,
                'description'    => $t->description,
                'channels_count' => $t->channels_count,
            ])
            ->toArray();
    }

    private function runTemplate(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for running templates.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $template = PipelineTemplate::where('slug', $args['template_slug'] ?? '')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return ['error' => 'Template not found or inactive.'];
        }

        if (!empty($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
        } else {
            $version = $prompt->active_version;
        }

        if (!$version) {
            return ['error' => 'Version not found.'];
        }

        $resultIds = $this->pipelineService->run(
            $template,
            $version,
            $args['variables'] ?? [],
            $user->id,
        );

        return ['result_ids' => $resultIds];
    }
}
