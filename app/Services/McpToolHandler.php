<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Models\Pipeline;
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
        private PipelineService $pipelineService,
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
            'get_results', 'list_branches', 'list_teams', 'list_pipelines', 'get_pipeline',
            'list_providers', 'get_evaluations', 'get_evaluation_prompt',
        ];

        $writeTools = [
            'create_prompt', 'save_version', 'store_result', 'update_result',
            'create_branch', 'share_prompt', 'run_pipeline', 'run_prompt',
            'evaluate_result', 'store_evaluation',
            'create_pipeline', 'update_pipeline', 'add_channel', 'update_channel',
            'pin_version', 'archive_version',
        ];

        $adminTools = [
            'delete_prompt', 'delete_result',
            'delete_pipeline', 'remove_channel',
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
                        'derived_from' => ['type' => 'string', 'description' => 'Slug of the source prompt this is derived from (e.g. for synthesis/best-of results)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name'        => 'run_prompt',
                'description' => 'Run a prompt through a URGE-registered LLM provider (server-side execution). Only use this when the user explicitly asks to run via a specific URGE provider (e.g. "run this with Mistral"). For normal execution, prefer: render_prompt → execute yourself → store_result.',
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
                'description' => 'List active LLM providers configured in URGE for server-side execution via run_prompt.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'evaluate_result',
                'description' => 'Evaluate an LLM result against its original prompt using configurable quality dimensions. Returns scores per dimension plus a composite score.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'result_id' => ['type' => 'integer', 'description' => 'The result ID to evaluate'],
                        'provider'  => ['type' => 'string', 'description' => 'Optional: override the default evaluator LLM provider name'],
                    ],
                    'required' => ['result_id'],
                ],
            ],
            [
                'name'        => 'store_evaluation',
                'description' => 'Store evaluation scores for a result. Use this when YOU (the LLM) have evaluated a result yourself instead of dispatching to an API. Provide dimension scores with reasoning.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'result_id' => ['type' => 'integer', 'description' => 'The result ID being evaluated'],
                        'scores'    => [
                            'type'  => 'array',
                            'description' => 'Array of dimension scores',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'dimension' => ['type' => 'string', 'description' => 'Dimension name (e.g. relevance, completeness, accuracy, clarity, conciseness)'],
                                    'score'     => ['type' => 'integer', 'description' => 'Score 1-5'],
                                    'reasoning' => ['type' => 'string', 'description' => 'Brief explanation for the score'],
                                ],
                                'required' => ['dimension', 'score'],
                            ],
                        ],
                        'evaluator_name' => ['type' => 'string', 'description' => 'Your name/model (e.g. "Claude Opus 4.6", "Le Chat"). Defaults to "mcp-client".'],
                    ],
                    'required' => ['result_id', 'scores'],
                ],
            ],
            [
                'name'        => 'get_evaluation_prompt',
                'description' => 'Get the evaluation prompt template, active dimensions, and weights. Use this to understand how to evaluate a result before calling store_evaluation.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'get_evaluations',
                'description' => 'Get evaluation scores for a result. Returns dimension scores, composite score, and reasoning.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'result_id' => ['type' => 'integer', 'description' => 'The result ID'],
                        'version'   => ['type' => 'integer', 'description' => 'Evaluation version number (defaults to latest)'],
                    ],
                    'required' => ['result_id'],
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
                'description' => 'Render a prompt template with variable substitution and include resolution. Returns an error if required variables are missing (provide values or set defaults in variable metadata). This is the standard way to execute prompts: call render_prompt to get the final text, execute it yourself (natively), then call store_result to save your response back to URGE.',
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
                'description' => 'Store an LLM response for a prompt version. Use this after running a prompt yourself: get_prompt or render_prompt → execute natively → store_result. Also used after running pipeline channels yourself via get_pipeline.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'             => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'            => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'version'          => ['type' => 'integer', 'description' => 'Version number (defaults to active version if omitted)'],
                        'response_text'    => ['type' => 'string', 'description' => 'The LLM response text'],
                        'provider'         => ['type' => 'string', 'description' => 'Provider/model name (e.g. "Claude Opus 4.6", "Le Chat")'],
                        'model'            => ['type' => 'string', 'description' => 'Model identifier'],
                        'rendered_content' => ['type' => 'string', 'description' => 'The rendered prompt that was sent to the LLM (with variables filled)'],
                        'variables_used'   => ['type' => 'object', 'description' => 'Variables used when rendering (key-value pairs)'],
                        'notes'            => ['type' => 'string', 'description' => 'Optional notes (e.g. pipeline channel role_label)'],
                        'branch'           => ['type' => 'string', 'description' => 'Branch name to scope version lookup'],
                        'run_source'       => ['type' => 'string', 'enum' => ['manual', 'scheduled'], 'description' => 'Tag the result by cadence: "scheduled" for periodic/cron-driven runs, "manual" for ad-hoc. Independent of the protocol the result arrives via — used downstream for time-series filtering.'],
                    ],
                    'required' => ['slug', 'response_text'],
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
                        'run_source' => ['type' => 'string', 'enum' => ['manual', 'scheduled'], 'description' => 'Filter by cadence tag (e.g. only periodic/scheduled results)'],
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
                'name'        => 'list_pipelines',
                'description' => 'List active pipelines with their channel counts.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'get_pipeline',
                'description' => 'Get a pipeline with all its channels, system prompts, and trigger order. Use this to run a pipeline yourself: read each channel, execute it natively, then store_result for each.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Pipeline slug'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'run_pipeline',
                'description' => 'Run a pipeline against a prompt version. Dispatches parallel LLM calls per channel and optional synthesis.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'          => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'         => ['type' => 'string', 'description' => 'Owner username. If omitted, searches your prompts first, then all visible.'],
                        'template_slug' => ['type' => 'string', 'description' => 'Pipeline slug'],
                        'version'       => ['type' => 'integer', 'description' => 'Optional version number (defaults to active version)'],
                        'variables'     => ['type' => 'object', 'description' => 'Key-value pairs for template variables'],
                        'run_source'    => ['type' => 'string', 'enum' => ['manual', 'scheduled'], 'description' => 'Tag every Result this run produces by cadence: "scheduled" for periodic/cron-driven runs, "manual" for ad-hoc.'],
                    ],
                    'required' => ['slug', 'template_slug'],
                ],
            ],
            [
                'name'        => 'create_pipeline',
                'description' => 'Create a new pipeline (run configuration with channels for multi-LLM dispatch).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'        => ['type' => 'string', 'description' => 'Pipeline name (e.g. "SWOT Analysis", "Multi-LLM Comparison")'],
                        'description' => ['type' => 'string', 'description' => 'What this pipeline does'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name'        => 'update_pipeline',
                'description' => 'Update a pipeline\'s name, description, or active status.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'        => ['type' => 'string', 'description' => 'Pipeline slug'],
                        'name'        => ['type' => 'string', 'description' => 'New name'],
                        'description' => ['type' => 'string', 'description' => 'New description'],
                        'is_active'   => ['type' => 'boolean', 'description' => 'Active status'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'delete_pipeline',
                'description' => 'Delete a pipeline permanently.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Pipeline slug'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'add_channel',
                'description' => 'Add a channel (LLM slot) to a pipeline. Each channel defines a provider, system prompt, and trigger type (parallel or synthesis). The system_prompt supports {{>slug}} includes — reference any fragment or prompt by slug for versioned, reusable context. By default a channel\'s user_prompt is the rendered prompt content; set input_source="result_history" to feed the channel a serialized batch of past Results instead (for trend / drift analysis pipelines).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'pipeline_slug'  => ['type' => 'string', 'description' => 'Pipeline slug'],
                        'role_label'     => ['type' => 'string', 'description' => 'Channel label (e.g. "strengths", "weaknesses", "summary")'],
                        'provider'       => ['type' => 'string', 'description' => 'LLM provider name. Use list_providers to see available.'],
                        'system_prompt'  => ['type' => 'string', 'description' => 'System prompt for this channel. Supports {{>slug}} includes for versioned fragments/prompts.'],
                        'input_source'   => ['type' => 'string', 'enum' => ['prompt', 'result_history'], 'description' => 'What the channel sees as user_prompt. "prompt" (default) = rendered prompt content. "result_history" = serialized batch of past Results matching input_filters.'],
                        'input_filters'  => ['type' => 'object', 'description' => 'When input_source=result_history, controls which Results are pulled. Keys: prompt_slug (defaults to the prompt being run), owner, since (ISO 8601 duration like "P30D"), limit (capped 100, default 50), run_source ("manual"|"scheduled"), include_failures (bool, default false).'],
                        'trigger'        => ['type' => 'string', 'enum' => ['parallel', 'synthesis'], 'description' => 'parallel = runs alongside others, synthesis = runs after all parallel channels complete'],
                        'sort_order'     => ['type' => 'integer', 'description' => 'Order within the pipeline (default 0)'],
                    ],
                    'required' => ['pipeline_slug', 'role_label', 'trigger'],
                ],
            ],
            [
                'name'        => 'update_channel',
                'description' => 'Update a channel\'s configuration within a pipeline.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'channel_id'     => ['type' => 'integer', 'description' => 'Channel ID'],
                        'role_label'     => ['type' => 'string', 'description' => 'New label'],
                        'provider'       => ['type' => 'string', 'description' => 'New LLM provider name'],
                        'system_prompt'  => ['type' => 'string', 'description' => 'New system prompt. Supports {{>slug}} includes.'],
                        'input_source'   => ['type' => 'string', 'enum' => ['prompt', 'result_history'], 'description' => 'New input source. See add_channel for semantics.'],
                        'input_filters'  => ['type' => 'object', 'description' => 'New filters when input_source=result_history. See add_channel.'],
                        'trigger'        => ['type' => 'string', 'enum' => ['parallel', 'synthesis'], 'description' => 'New trigger type'],
                        'sort_order'     => ['type' => 'integer', 'description' => 'New sort order'],
                    ],
                    'required' => ['channel_id'],
                ],
            ],
            [
                'name'        => 'remove_channel',
                'description' => 'Remove a channel from a pipeline.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'channel_id' => ['type' => 'integer', 'description' => 'Channel ID to remove'],
                    ],
                    'required' => ['channel_id'],
                ],
            ],
            [
                'name'        => 'pin_version',
                'description' => 'Pin a specific version as the active version for a prompt, or unpin to use latest.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'       => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'      => ['type' => 'string', 'description' => 'Owner username'],
                        'version_id' => ['type' => 'integer', 'description' => 'Version ID to pin. Omit or null to unpin.'],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name'        => 'archive_version',
                'description' => 'Archive or unarchive a prompt version. Archived versions are flagged but not deleted.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'    => ['type' => 'string', 'description' => 'The prompt slug'],
                        'owner'   => ['type' => 'string', 'description' => 'Owner username'],
                        'version' => ['type' => 'integer', 'description' => 'Version number to archive/unarchive'],
                    ],
                    'required' => ['slug', 'version'],
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
            'evaluate_result'      => $this->evaluateResult($arguments, $user),
            'store_evaluation'     => $this->storeEvaluation($arguments, $user),
            'get_evaluation_prompt' => $this->getEvaluationPrompt($arguments, $user),
            'get_evaluations'      => $this->getEvaluationsForResult($arguments, $user),
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
            'list_pipelines'   => $this->listPipelines($arguments, $user),
            'get_pipeline'     => $this->getPipeline($arguments, $user),
            'run_pipeline'     => $this->runPipeline($arguments, $user),
            'create_pipeline'  => $this->createPipelineTool($arguments, $user),
            'update_pipeline'  => $this->updatePipelineTool($arguments, $user),
            'delete_pipeline'  => $this->deletePipelineTool($arguments, $user),
            'add_channel'      => $this->addChannelTool($arguments, $user),
            'update_channel'   => $this->updateChannelTool($arguments, $user),
            'remove_channel'   => $this->removeChannelTool($arguments, $user),
            'pin_version'      => $this->pinVersion($arguments, $user),
            'archive_version'  => $this->archiveVersion($arguments, $user),
            default            => ['error' => "Unknown tool: {$name}"],
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

        $derivedFromId = null;
        if (!empty($args['derived_from'])) {
            $sourcePrompt = Prompt::where('slug', $args['derived_from'])->first();
            if ($sourcePrompt) {
                $derivedFromId = $sourcePrompt->id;
            }
        }

        $prompt = Prompt::create([
            'name'        => $name,
            'type'        => $args['type'] ?? 'prompt',
            'description' => $args['description'] ?? null,
            'created_by'  => $user->id,
            'derived_from_prompt_id' => $derivedFromId,
        ]);

        $prompt->load(['creator', 'derivedFrom']);

        $result = [
            'id'          => $prompt->id,
            'name'        => $prompt->name,
            'slug'        => $prompt->slug,
            'type'        => $prompt->type,
            'description' => $prompt->description,
            'owner'       => $prompt->creator?->username ?? $prompt->creator?->name,
            'derived_from' => $prompt->derivedFrom?->slug,
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

        // Render template with variables (strict: reject if required vars missing)
        $variables = $args['variables'] ?? [];
        try {
            $renderResult = $this->templateEngine->render(
                $version->content,
                $variables,
                $version->variable_metadata,
                $user,
                strict: true,
            );
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
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

    private function evaluateResult(array $args, ?User $user = null): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $resultId = $args['result_id'] ?? null;
        if (!$resultId) {
            return ['error' => 'result_id is required.'];
        }

        $result = Result::find($resultId);
        if (!$result) {
            return ['error' => "Result {$resultId} not found."];
        }

        $providerOverride = null;
        if (!empty($args['provider'])) {
            $providerOverride = \App\Models\LlmProvider::where('name', 'like', $args['provider'])
                ->where('is_active', true)
                ->first();
            if (!$providerOverride) {
                return ['error' => "Provider '{$args['provider']}' not found or inactive."];
            }
        }

        $evaluationService = app(\App\Services\EvaluationService::class);
        return $evaluationService->evaluate($result, $user, $providerOverride);
    }

    private function getEvaluationsForResult(array $args, ?User $user = null): array
    {
        $resultId = $args['result_id'] ?? null;
        if (!$resultId) {
            return ['error' => 'result_id is required.'];
        }

        $result = Result::find($resultId);
        if (!$result) {
            return ['error' => "Result {$resultId} not found."];
        }

        $evaluationService = app(\App\Services\EvaluationService::class);
        return $evaluationService->getEvaluations($resultId, $args['version'] ?? null);
    }

    private function storeEvaluation(array $args, ?User $user = null): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $resultId = $args['result_id'] ?? null;
        if (!$resultId) {
            return ['error' => 'result_id is required.'];
        }

        $result = Result::find($resultId);
        if (!$result) {
            return ['error' => "Result {$resultId} not found."];
        }

        $scores = $args['scores'] ?? [];
        if (empty($scores)) {
            return ['error' => 'scores array is required and must not be empty.'];
        }

        $evaluatorName = $args['evaluator_name'] ?? 'mcp-client';

        // Get active dimensions and their weights
        $evaluationService = app(\App\Services\EvaluationService::class);
        $activeDimensions = $evaluationService->getActiveDimensions();
        $dimensionMap = [];
        foreach ($activeDimensions as $d) {
            $dimensionMap[$d['name']] = $d;
        }

        // Determine next evaluation version
        $latestVersion = \App\Models\ResultEvaluation::where('result_id', $resultId)
            ->max('evaluation_version') ?? 0;
        $evalVersion = $latestVersion + 1;

        $storedScores = [];
        foreach ($scores as $score) {
            $dimName = $score['dimension'] ?? '';
            $dimScore = max(1, min(5, (int) ($score['score'] ?? 0)));
            $reasoning = $score['reasoning'] ?? null;

            if (!$dimName || !$dimScore) {
                continue;
            }

            $weight = isset($dimensionMap[$dimName]) ? $dimensionMap[$dimName]['weight'] : 1.0;

            \App\Models\ResultEvaluation::create([
                'result_id'           => $resultId,
                'evaluation_version'  => $evalVersion,
                'evaluator_provider'  => $evaluatorName,
                'evaluator_model'     => $evaluatorName,
                'dimension'           => $dimName,
                'score'               => $dimScore,
                'reasoning'           => $reasoning,
                'weight'              => $weight,
                'created_by'          => $user->id,
            ]);

            $storedScores[] = [
                'dimension' => $dimName,
                'score'     => $dimScore,
                'weight'    => (float) $weight,
                'reasoning' => $reasoning,
            ];
        }

        if (empty($storedScores)) {
            return ['error' => 'No valid scores provided.'];
        }

        // Include human rating if exists
        if ($result->rating) {
            $humanDim = $dimensionMap['human'] ?? null;
            if ($humanDim && ($humanDim['enabled'] ?? true)) {
                \App\Models\ResultEvaluation::updateOrCreate(
                    ['result_id' => $resultId, 'evaluation_version' => $evalVersion, 'dimension' => 'human'],
                    [
                        'evaluator_provider' => 'human',
                        'evaluator_model'    => 'human',
                        'score'              => $result->rating,
                        'weight'             => $humanDim['weight'] ?? 1.0,
                        'created_by'         => $user->id,
                    ],
                );
                $storedScores[] = [
                    'dimension' => 'human',
                    'score'     => $result->rating,
                    'weight'    => $humanDim['weight'] ?? 1.0,
                    'reasoning' => null,
                ];
            }
        }

        $composite = \App\Models\ResultEvaluation::compositeScore($resultId, $evalVersion);

        return [
            'evaluation_version' => $evalVersion,
            'composite_score'    => $composite,
            'scores'             => $storedScores,
            'evaluator'          => $evaluatorName,
        ];
    }

    private function getEvaluationPrompt(array $args, ?User $user = null): array
    {
        $evaluationService = app(\App\Services\EvaluationService::class);
        $dimensions = $evaluationService->getActiveDimensions();

        // Get the evaluation prompt content
        $promptSlug = \App\Models\EvaluationSetting::get('prompt_slug', 'system-evaluation-template');
        $prompt = \App\Models\Prompt::where('slug', $promptSlug)->first();
        $promptContent = $prompt?->activeVersion?->content ?? null;

        // Filter out human dimension (LLM can't score that)
        $llmDimensions = array_values(array_filter($dimensions, fn ($d) => $d['name'] !== 'human'));

        return [
            'prompt_slug'    => $promptSlug,
            'prompt_content' => $promptContent,
            'dimensions'     => array_map(fn ($d) => [
                'name'        => $d['name'],
                'description' => $d['description'],
                'weight'      => $d['weight'],
            ], $llmDimensions),
            'scale'          => '1 = poor, 2 = below average, 3 = adequate, 4 = good, 5 = excellent',
            'instructions'   => 'Evaluate the result, then call store_evaluation with your scores.',
        ];
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

        try {
            return $this->templateEngine->render($version->content, $variables, $version->variable_metadata, $user, strict: true);
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
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

        // Version: use specified, or resolve from branch, or default to active
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

        $runSource = $args['run_source'] ?? null;
        if ($runSource !== null && !in_array($runSource, ['manual', 'scheduled'], true)) {
            return ['error' => 'run_source must be "manual" or "scheduled".'];
        }

        $result = Result::create([
            'prompt_id'         => $prompt->id,
            'prompt_version_id' => $version->id,
            'source'            => 'mcp',
            'run_source'        => $runSource,
            'response_text'     => $args['response_text'],
            'provider_name'     => $args['provider'] ?? null,
            'model_name'        => $args['model'] ?? null,
            'rendered_content'  => $args['rendered_content'] ?? null,
            'variables_used'    => !empty($args['variables_used']) ? $args['variables_used'] : null,
            'notes'             => $args['notes'] ?? null,
            'created_by'        => $user->id,
        ]);

        return [
            'id'             => $result->id,
            'prompt_slug'    => $prompt->slug,
            'version_number' => $version->version_number,
            'created'        => true,
        ];
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

        if (!empty($args['run_source']) && in_array($args['run_source'], ['manual', 'scheduled'], true)) {
            $query->where('run_source', $args['run_source']);
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

    private function listPipelines(array $args, ?User $user): array
    {
        return Pipeline::where('is_active', true)
            ->with(['channels.llmProvider'])
            ->withCount('channels')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'slug'                => $t->slug,
                'name'                => $t->name,
                'description'         => $t->description,
                'channels_count'      => $t->channels_count,
                'has_client_channels' => $t->channels->contains(
                    fn ($c) => $c->execution_mode === 'client'
                ),
            ])
            ->toArray();
    }

    private function getPipeline(array $args, ?User $user): array
    {
        $pipeline = Pipeline::where('slug', $args['slug'] ?? '')->first();
        if (!$pipeline) {
            return ['error' => 'Pipeline not found.'];
        }

        $pipeline->load(['channels.llmProvider']);

        $parallel = [];
        $synthesis = null;

        $hasClient = false;
        foreach ($pipeline->channels as $channel) {
            $mode = $channel->execution_mode;
            if ($mode === 'client') {
                $hasClient = true;
            }

            $channelData = [
                'id'             => $channel->id,
                'role_label'     => $channel->role_label,
                'system_prompt'  => $channel->system_prompt,
                'input_source'   => $channel->input_source ?? 'prompt',
                'input_filters'  => $channel->input_filters,
                'trigger'        => $channel->trigger,
                'sort_order'     => $channel->sort_order,
                'provider'       => $channel->llmProvider?->name,
                'model'          => $channel->llmProvider?->model,
                'execution_mode' => $mode,
            ];

            if ($channel->trigger === 'synthesis') {
                $synthesis = $channelData;
            } else {
                $parallel[] = $channelData;
            }
        }

        $instructions = $hasClient
            ? 'This pipeline has channels marked execution_mode="client" — they have no active LLM provider configured on the server, so YOU must run them locally. For each client channel: use its system_prompt as context, run it against the prompt content, and call store_result with the response. For execution_mode="server" channels you can call run_pipeline and the server will dispatch them.'
            : 'All channels are execution_mode="server". Call run_pipeline to have the server dispatch them, or run them yourself for free and call store_result for each output.';

        return [
            'slug'                => $pipeline->slug,
            'name'                => $pipeline->name,
            'description'         => $pipeline->description,
            'is_active'           => $pipeline->is_active,
            'parallel_channels'   => $parallel,
            'synthesis_channel'   => $synthesis,
            'has_client_channels' => $hasClient,
            'instructions'        => $instructions,
        ];
    }

    private function runPipeline(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'User context required for running pipelines.'];
        }

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) {
            return ['error' => 'Prompt not found.'];
        }

        $pipeline = Pipeline::where('slug', $args['template_slug'] ?? '')
            ->where('is_active', true)
            ->first();

        if (!$pipeline) {
            return ['error' => 'Pipeline not found or inactive.'];
        }

        if (!empty($args['version'])) {
            $version = $prompt->versions()->where('version_number', $args['version'])->first();
        } else {
            $version = $prompt->active_version;
        }

        if (!$version) {
            return ['error' => 'Version not found.'];
        }

        $runSource = $args['run_source'] ?? null;
        if ($runSource !== null && !in_array($runSource, ['manual', 'scheduled'], true)) {
            return ['error' => 'run_source must be "manual" or "scheduled".'];
        }

        $runResult = $this->pipelineService->run(
            $pipeline,
            $version,
            $args['variables'] ?? [],
            $user->id,
            $runSource,
        );

        $response = [
            'result_ids'              => $runResult['result_ids'],
            'pending_client_channels' => $runResult['pending_client_channels'],
        ];

        if (!empty($runResult['pending_client_channels'])) {
            $response['instructions'] = 'Some channels could not be dispatched server-side because no active LLM provider is configured. For each entry in pending_client_channels: run user_prompt against system_prompt locally, then call store_result with role_label and the response_text. For synthesis channels with user_prompt=null, call get_results for this prompt+version to gather parallel outputs and build the synthesis input yourself.';
        }

        return $response;
    }

    private function createPipelineTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $name = $args['name'] ?? '';
        if (!$name) {
            return ['error' => 'name is required.'];
        }

        $pipeline = Pipeline::create([
            'name'        => $name,
            'description' => $args['description'] ?? null,
            'created_by'  => $user->id,
        ]);

        return [
            'id'          => $pipeline->id,
            'name'        => $pipeline->name,
            'slug'        => $pipeline->slug,
            'description' => $pipeline->description,
            'is_active'   => $pipeline->is_active,
        ];
    }

    private function updatePipelineTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $pipeline = Pipeline::where('slug', $args['slug'] ?? '')->first();
        if (!$pipeline) {
            return ['error' => 'Pipeline not found.'];
        }

        $updates = [];
        if (isset($args['name'])) $updates['name'] = $args['name'];
        if (isset($args['description'])) $updates['description'] = $args['description'];
        if (isset($args['is_active'])) $updates['is_active'] = (bool) $args['is_active'];

        if (!empty($updates)) {
            $pipeline->update($updates);
        }

        return [
            'id'          => $pipeline->id,
            'name'        => $pipeline->name,
            'slug'        => $pipeline->slug,
            'description' => $pipeline->description,
            'is_active'   => $pipeline->is_active,
        ];
    }

    private function deletePipelineTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $pipeline = Pipeline::where('slug', $args['slug'] ?? '')->first();
        if (!$pipeline) {
            return ['error' => 'Pipeline not found.'];
        }

        $pipeline->delete();

        return ['message' => "Pipeline '{$pipeline->name}' deleted."];
    }

    private function addChannelTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $pipeline = Pipeline::where('slug', $args['pipeline_slug'] ?? '')->first();
        if (!$pipeline) {
            return ['error' => 'Pipeline not found.'];
        }

        $providerId = null;
        if (!empty($args['provider'])) {
            $provider = LlmProvider::where('name', 'like', $args['provider'])
                ->where('is_active', true)
                ->first();
            if (!$provider) {
                return ['error' => "Provider '{$args['provider']}' not found or inactive."];
            }
            $providerId = $provider->id;
        }

        $inputSource = $args['input_source'] ?? 'prompt';
        if (!in_array($inputSource, ['prompt', 'result_history'], true)) {
            return ['error' => 'input_source must be "prompt" or "result_history".'];
        }

        $channel = PipelineChannel::create([
            'pipeline_id'     => $pipeline->id,
            'role_label'      => $args['role_label'] ?? '',
            'llm_provider_id' => $providerId,
            'system_prompt'   => $args['system_prompt'] ?? null,
            'input_source'    => $inputSource,
            'input_filters'   => $args['input_filters'] ?? null,
            'trigger'         => $args['trigger'] ?? 'parallel',
            'sort_order'      => $args['sort_order'] ?? 0,
        ]);

        return [
            'id'             => $channel->id,
            'pipeline_slug'  => $pipeline->slug,
            'role_label'     => $channel->role_label,
            'provider'       => $provider->name ?? null,
            'input_source'   => $channel->input_source,
            'input_filters'  => $channel->input_filters,
            'trigger'        => $channel->trigger,
            'sort_order'     => $channel->sort_order,
        ];
    }

    private function updateChannelTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $channel = PipelineChannel::find($args['channel_id'] ?? 0);
        if (!$channel) {
            return ['error' => 'Channel not found.'];
        }

        $updates = [];
        if (isset($args['role_label'])) $updates['role_label'] = $args['role_label'];
        if (isset($args['system_prompt'])) $updates['system_prompt'] = $args['system_prompt'];
        if (isset($args['trigger'])) $updates['trigger'] = $args['trigger'];
        if (isset($args['sort_order'])) $updates['sort_order'] = $args['sort_order'];

        if (isset($args['input_source'])) {
            if (!in_array($args['input_source'], ['prompt', 'result_history'], true)) {
                return ['error' => 'input_source must be "prompt" or "result_history".'];
            }
            $updates['input_source'] = $args['input_source'];
        }
        if (array_key_exists('input_filters', $args)) {
            $updates['input_filters'] = $args['input_filters'];
        }

        if (!empty($args['provider'])) {
            $provider = LlmProvider::where('name', 'like', $args['provider'])
                ->where('is_active', true)
                ->first();
            if (!$provider) {
                return ['error' => "Provider '{$args['provider']}' not found or inactive."];
            }
            $updates['llm_provider_id'] = $provider->id;
        }

        if (!empty($updates)) {
            $channel->update($updates);
        }

        $channel->load('llmProvider');

        return [
            'id'            => $channel->id,
            'role_label'    => $channel->role_label,
            'provider'      => $channel->llmProvider?->name,
            'input_source'  => $channel->input_source,
            'input_filters' => $channel->input_filters,
            'trigger'       => $channel->trigger,
            'sort_order'    => $channel->sort_order,
            'system_prompt' => $channel->system_prompt,
        ];
    }

    private function removeChannelTool(array $args, ?User $user): array
    {
        if (!$user) {
            return ['error' => 'Authentication required.'];
        }

        $channel = PipelineChannel::find($args['channel_id'] ?? 0);
        if (!$channel) {
            return ['error' => 'Channel not found.'];
        }

        $label = $channel->role_label;
        $channel->delete();

        return ['message' => "Channel '{$label}' removed."];
    }

    private function pinVersion(array $args, ?User $user): array
    {
        if (!$user) return ['error' => 'Authentication required.'];

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) return ['error' => 'Prompt not found.'];

        $versionId = $args['version_id'] ?? null;

        if ($versionId) {
            $version = $prompt->versions()->where('id', $versionId)->first();
            if (!$version) return ['error' => 'Version not found.'];
        }

        $prompt->update(['pinned_version_id' => $versionId]);

        return [
            'pinned_version_id' => $prompt->pinned_version_id,
            'message' => $versionId ? "Pinned to version #{$version->version_number}." : 'Unpinned — using latest.',
        ];
    }

    private function archiveVersion(array $args, ?User $user): array
    {
        if (!$user) return ['error' => 'Authentication required.'];

        $prompt = $this->resolvePrompt($args['slug'] ?? '', $args['owner'] ?? null, $user);
        if (!$prompt) return ['error' => 'Prompt not found.'];

        $version = $prompt->versions()->where('version_number', $args['version'])->first();
        if (!$version) return ['error' => 'Version not found.'];

        if ($version->archived_at) {
            $version->archived_at = null;
            $version->save();
            return ['message' => "Version {$args['version']} unarchived.", 'archived' => false];
        }

        $version->archived_at = now();
        $version->save();
        return ['message' => "Version {$args['version']} archived.", 'archived' => true];
    }
}
