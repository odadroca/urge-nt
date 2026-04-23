# Result Evaluation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add LLM-powered result evaluation with configurable dimensions, versioned evaluation prompts, human-in-the-loop scoring, API + MCP tools, canvas integration, and settings UI.

**Architecture:** Evaluation is a pipeline template run. The evaluation prompt is a regular URGE prompt (versioned). Scores stored in `result_evaluations` table with versioning. Human star-ratings unified as a 6th dimension. Settings stored in `evaluation_settings` table. Exposed via REST API, MCP tools, and canvas/workspace UI.

**Tech Stack:** Laravel 12 / PHP 8.3+, React 19, Livewire 3, SQLite
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-15-result-evaluation-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `database/migrations/xxxx_create_evaluation_tables.php` | `result_evaluations` + `evaluation_settings` tables |
| `app/Models/ResultEvaluation.php` | Evaluation score model |
| `app/Models/EvaluationSetting.php` | Key-value settings model |
| `app/Services/EvaluationService.php` | Orchestrates evaluation: render prompt, dispatch, parse JSON, store scores |
| `app/Http/Controllers/Api/EvaluationController.php` | REST API: evaluate, list, latest, version |
| `app/Livewire/Settings/Evaluation.php` | Settings tab component |
| `resources/views/livewire/settings/evaluation.blade.php` | Settings tab view |
| `app/Console/Commands/SeedEvaluationCommand.php` | `urge:seed-evaluation` bootstrap command |
| `tests/Feature/EvaluationTest.php` | API + service + MCP tests |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/McpToolHandler.php` | Add `evaluate_result`, `get_evaluations` tools |
| `app/Livewire/Settings.php` | Add 'evaluation' tab |
| `resources/views/livewire/settings.blade.php` | Add evaluation tab button + content |
| `app/Http/Controllers/Api/GraphController.php` | Include evaluation score in prompt nodes |
| `resources/js/spa/components/canvas/nodes/PromptNode.jsx` | Score badge |
| `resources/js/spa/components/workspace/ResultsPanel.jsx` | Evaluation scores on result cards |
| `routes/api.php` | Evaluation endpoints |
| `config/urge.php` | Default evaluation config |

---

## Task 1: Database Tables + Models

**Files:**
- Create: `database/migrations/2026_04_15_100000_create_evaluation_tables.php`
- Create: `app/Models/ResultEvaluation.php`
- Create: `app/Models/EvaluationSetting.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('evaluation_version')->default(1);
            $table->string('pipeline_run_id', 36)->nullable();
            $table->foreignId('evaluation_prompt_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->string('evaluator_provider');
            $table->string('evaluator_model');
            $table->string('dimension', 50);
            $table->tinyInteger('score');
            $table->text('reasoning')->nullable();
            $table->decimal('weight', 3, 2)->default(1.00);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['result_id', 'evaluation_version', 'dimension'], 'eval_version_dimension_unique');
            $table->index(['result_id', 'evaluation_version']);
        });

        Schema::create('evaluation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_evaluations');
        Schema::dropIfExists('evaluation_settings');
    }
};
```

- [ ] **Step 2: Create ResultEvaluation model**

Create `app/Models/ResultEvaluation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultEvaluation extends Model
{
    protected $fillable = [
        'result_id', 'evaluation_version', 'pipeline_run_id',
        'evaluation_prompt_version_id', 'evaluator_provider',
        'evaluator_model', 'dimension', 'score', 'reasoning',
        'weight', 'created_by',
    ];

    protected $casts = [
        'score' => 'integer',
        'weight' => 'decimal:2',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function evaluationPromptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'evaluation_prompt_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function compositeScore(int $resultId, ?int $version = null): ?float
    {
        $query = static::where('result_id', $resultId);

        if ($version) {
            $query->where('evaluation_version', $version);
        } else {
            $latestVersion = static::where('result_id', $resultId)->max('evaluation_version');
            if (!$latestVersion) {
                return null;
            }
            $query->where('evaluation_version', $latestVersion);
        }

        $scores = $query->get(['score', 'weight']);
        if ($scores->isEmpty()) {
            return null;
        }

        $totalWeight = $scores->sum('weight');
        if ($totalWeight == 0) {
            return null;
        }

        return round($scores->sum(fn ($s) => $s->score * $s->weight) / $totalWeight, 2);
    }
}
```

- [ ] **Step 3: Create EvaluationSetting model**

Create `app/Models/EvaluationSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
```

- [ ] **Step 4: Run migration**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan migrate
```

- [ ] **Step 5: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add database/migrations/*create_evaluation_tables* app/Models/ResultEvaluation.php app/Models/EvaluationSetting.php
git commit -m "feat: evaluation tables and models (result_evaluations, evaluation_settings)"
```

---

## Task 2: EvaluationService

**Files:**
- Create: `app/Services/EvaluationService.php`
- Modify: `config/urge.php`

- [ ] **Step 1: Add evaluation config**

Append to `config/urge.php` before closing `];`:

```php
    // Evaluation
    'evaluation' => [
        'default_dimensions' => [
            ['name' => 'relevance', 'description' => 'Does the response address what the prompt asked for?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'completeness', 'description' => 'Are all parts of the prompt addressed? Nothing missing?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'accuracy', 'description' => 'Is the information correct and well-reasoned?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'clarity', 'description' => 'Is the response well-structured and easy to follow?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'conciseness', 'description' => 'Right amount of detail — not too verbose, not too sparse?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'human', 'description' => 'Human star-rating of the result.', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
        ],
    ],
```

- [ ] **Step 2: Create EvaluationService**

Create `app/Services/EvaluationService.php`:

```php
<?php

namespace App\Services;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Result;
use App\Models\ResultEvaluation;
use App\Models\User;

class EvaluationService
{
    public function __construct(
        private LlmDispatchService $dispatchService,
        private TemplateEngine $templateEngine,
    ) {}

    public function evaluate(Result $result, User $user, ?LlmProvider $providerOverride = null): array
    {
        if (!$this->isEnabled()) {
            return ['error' => 'Evaluation is not enabled. Enable it in Settings > Evaluation.'];
        }

        $result->load(['prompt', 'promptVersion']);

        if (!$result->prompt || !$result->promptVersion) {
            return ['error' => 'Result has no associated prompt or version.'];
        }

        $provider = $providerOverride ?? $this->getDefaultProvider();
        if (!$provider) {
            return ['error' => 'No evaluation provider configured. Set one in Settings > Evaluation.'];
        }

        $dimensions = $this->getActiveDimensions();
        $llmDimensions = array_filter($dimensions, fn ($d) => $d['name'] !== 'human');

        if (empty($llmDimensions)) {
            return ['error' => 'No LLM evaluation dimensions enabled.'];
        }

        // Build evaluation prompt
        $dimensionsJson = json_encode(array_values(array_map(fn ($d) => [
            'name' => $d['name'],
            'description' => $d['description'],
        ], $llmDimensions)), JSON_PRETTY_PRINT);

        $renderedPrompt = $result->rendered_content ?? $result->promptVersion->content;

        $systemPrompt = 'You are an expert prompt evaluator. Evaluate the LLM response against the original prompt on the given dimensions. Return ONLY valid JSON with no other text.';

        $userPrompt = "ORIGINAL PROMPT:\n---\n{$renderedPrompt}\n---\n\n"
            . "LLM RESPONSE:\n---\n{$result->response_text}\n---\n\n"
            . "DIMENSIONS TO EVALUATE:\n{$dimensionsJson}\n\n"
            . "RATING SCALE: 1 = poor, 2 = below average, 3 = adequate, 4 = good, 5 = excellent\n\n"
            . "Return JSON in this exact format:\n"
            . '{"scores":[{"dimension":"name","score":N,"reasoning":"brief explanation"}]}';

        // Check for custom evaluation prompt
        $evalPromptSlug = EvaluationSetting::get('prompt_slug', 'system/evaluation-template');
        $evalPrompt = \App\Models\Prompt::where('slug', $evalPromptSlug)->first();
        $evalPromptVersion = null;

        if ($evalPrompt && $evalPrompt->activeVersion) {
            $evalPromptVersion = $evalPrompt->activeVersion;
            $rendered = $this->templateEngine->render(
                $evalPromptVersion->content,
                [
                    'original_prompt' => $renderedPrompt,
                    'response_text' => $result->response_text,
                    'dimensions' => $dimensionsJson,
                    'scale' => '1 = poor, 2 = below average, 3 = adequate, 4 = good, 5 = excellent',
                ],
                null,
                $user,
            );
            $userPrompt = $rendered['rendered'];
        }

        // Dispatch to evaluator LLM
        $llmResult = $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $userPrompt);

        if (!$llmResult->success) {
            return ['error' => "Evaluation LLM call failed: {$llmResult->error}"];
        }

        // Parse JSON response
        $scores = $this->parseScores($llmResult->text, $llmDimensions);
        if ($scores === null) {
            return ['error' => 'Failed to parse evaluation response as JSON.', 'raw_response' => $llmResult->text];
        }

        // Determine evaluation version
        $latestVersion = ResultEvaluation::where('result_id', $result->id)
            ->max('evaluation_version') ?? 0;
        $evalVersion = $latestVersion + 1;

        // Store scores
        $storedScores = [];
        foreach ($scores as $score) {
            $dimension = $this->findDimension($score['dimension'], $dimensions);
            $weight = $dimension ? $dimension['weight'] : 1.0;

            $eval = ResultEvaluation::create([
                'result_id'                    => $result->id,
                'evaluation_version'           => $evalVersion,
                'evaluation_prompt_version_id' => $evalPromptVersion?->id,
                'evaluator_provider'           => $provider->name,
                'evaluator_model'              => $llmResult->modelUsed ?? $provider->model,
                'dimension'                    => $score['dimension'],
                'score'                        => max(1, min(5, (int) $score['score'])),
                'reasoning'                    => $score['reasoning'] ?? null,
                'weight'                       => $weight,
                'created_by'                   => $user->id,
            ]);

            $storedScores[] = [
                'dimension' => $eval->dimension,
                'score'     => $eval->score,
                'weight'    => (float) $eval->weight,
                'reasoning' => $eval->reasoning,
            ];
        }

        // Include human dimension if result has a star rating
        if ($result->rating) {
            $humanDim = $this->findDimension('human', $dimensions);
            if ($humanDim && $humanDim['enabled']) {
                ResultEvaluation::updateOrCreate(
                    ['result_id' => $result->id, 'evaluation_version' => $evalVersion, 'dimension' => 'human'],
                    [
                        'evaluator_provider' => 'human',
                        'evaluator_model'    => 'human',
                        'score'              => $result->rating,
                        'weight'             => $humanDim['weight'],
                        'created_by'         => $user->id,
                    ],
                );
                $storedScores[] = [
                    'dimension' => 'human',
                    'score'     => $result->rating,
                    'weight'    => $humanDim['weight'],
                    'reasoning' => null,
                ];
            }
        }

        $composite = ResultEvaluation::compositeScore($result->id, $evalVersion);

        return [
            'evaluation_version'           => $evalVersion,
            'composite_score'              => $composite,
            'scores'                       => $storedScores,
            'evaluator_provider'           => $provider->name,
            'evaluator_model'              => $llmResult->modelUsed ?? $provider->model,
            'evaluation_prompt_version_id' => $evalPromptVersion?->id,
        ];
    }

    public function getEvaluations(int $resultId, ?int $version = null): array
    {
        if ($version) {
            $scores = ResultEvaluation::where('result_id', $resultId)
                ->where('evaluation_version', $version)
                ->get();
        } else {
            $latestVersion = ResultEvaluation::where('result_id', $resultId)
                ->max('evaluation_version');
            if (!$latestVersion) {
                return ['evaluation_version' => null, 'composite_score' => null, 'scores' => []];
            }
            $scores = ResultEvaluation::where('result_id', $resultId)
                ->where('evaluation_version', $latestVersion)
                ->get();
            $version = $latestVersion;
        }

        return [
            'evaluation_version' => $version,
            'composite_score'    => ResultEvaluation::compositeScore($resultId, $version),
            'scores'             => $scores->map(fn ($s) => [
                'dimension'          => $s->dimension,
                'score'              => $s->score,
                'weight'             => (float) $s->weight,
                'reasoning'          => $s->reasoning,
                'evaluator_provider' => $s->evaluator_provider,
                'evaluator_model'    => $s->evaluator_model,
            ])->values()->toArray(),
        ];
    }

    public function listVersions(int $resultId): array
    {
        return ResultEvaluation::where('result_id', $resultId)
            ->selectRaw('evaluation_version, COUNT(*) as scores_count, MIN(created_at) as created_at')
            ->groupBy('evaluation_version')
            ->orderByDesc('evaluation_version')
            ->get()
            ->map(fn ($row) => [
                'evaluation_version' => $row->evaluation_version,
                'scores_count'       => $row->scores_count,
                'composite_score'    => ResultEvaluation::compositeScore($resultId, $row->evaluation_version),
                'created_at'         => $row->created_at,
            ])
            ->toArray();
    }

    public function syncHumanRating(Result $result, User $user): void
    {
        if (!$result->rating) {
            return;
        }

        $latestVersion = ResultEvaluation::where('result_id', $result->id)
            ->max('evaluation_version');

        if (!$latestVersion) {
            return;
        }

        $dimensions = $this->getActiveDimensions();
        $humanDim = $this->findDimension('human', $dimensions);
        if (!$humanDim || !$humanDim['enabled']) {
            return;
        }

        ResultEvaluation::updateOrCreate(
            ['result_id' => $result->id, 'evaluation_version' => $latestVersion, 'dimension' => 'human'],
            [
                'evaluator_provider' => 'human',
                'evaluator_model'    => 'human',
                'score'              => $result->rating,
                'weight'             => $humanDim['weight'],
                'created_by'         => $user->id,
            ],
        );
    }

    public function isEnabled(): bool
    {
        return (bool) EvaluationSetting::get('enabled', false);
    }

    public function getDefaultProvider(): ?LlmProvider
    {
        $providerId = EvaluationSetting::get('default_provider_id');
        if (!$providerId) {
            return null;
        }

        return LlmProvider::where('id', $providerId)->where('is_active', true)->first();
    }

    public function getActiveDimensions(): array
    {
        $dimensions = EvaluationSetting::get('dimensions', config('urge.evaluation.default_dimensions'));
        return array_filter($dimensions, fn ($d) => $d['enabled'] ?? true);
    }

    private function parseScores(string $text, array $expectedDimensions): ?array
    {
        // Extract JSON from response (may have surrounding text)
        if (preg_match('/\{[\s\S]*"scores"[\s\S]*\}/', $text, $matches)) {
            $text = $matches[0];
        }

        $data = json_decode($text, true);
        if (!$data || !isset($data['scores']) || !is_array($data['scores'])) {
            return null;
        }

        $validNames = array_column($expectedDimensions, 'name');
        $scores = [];

        foreach ($data['scores'] as $score) {
            if (!isset($score['dimension'], $score['score'])) {
                continue;
            }
            if (!in_array($score['dimension'], $validNames)) {
                continue;
            }
            $scores[] = [
                'dimension' => $score['dimension'],
                'score'     => (int) $score['score'],
                'reasoning' => $score['reasoning'] ?? null,
            ];
        }

        return !empty($scores) ? $scores : null;
    }

    private function findDimension(string $name, array $dimensions): ?array
    {
        foreach ($dimensions as $d) {
            if ($d['name'] === $name) {
                return $d;
            }
        }
        return null;
    }
}
```

- [ ] **Step 3: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Services/EvaluationService.php config/urge.php
git commit -m "feat: EvaluationService with LLM dispatch, score parsing, human rating sync"
```

---

## Task 3: REST API Endpoints

**Files:**
- Create: `app/Http/Controllers/Api/EvaluationController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create EvaluationController**

Create `app/Http/Controllers/Api/EvaluationController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Result;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends ApiController
{
    public function __construct(private EvaluationService $evaluationService) {}

    public function evaluate(Request $request, Result $result): JsonResponse
    {
        $providerOverride = null;
        if ($providerName = $request->input('provider')) {
            $providerOverride = \App\Models\LlmProvider::where('name', 'like', $providerName)
                ->where('is_active', true)
                ->first();
            if (!$providerOverride) {
                return $this->error("Provider '{$providerName}' not found or inactive.", 400);
            }
        }

        $result = $this->evaluationService->evaluate($result, $request->user(), $providerOverride);

        if (isset($result['error'])) {
            return $this->error($result['error'], 400);
        }

        return $this->success($result, 201);
    }

    public function index(Result $result): JsonResponse
    {
        return $this->success($this->evaluationService->listVersions($result->id));
    }

    public function latest(Result $result): JsonResponse
    {
        return $this->success($this->evaluationService->getEvaluations($result->id));
    }

    public function show(Result $result, int $version): JsonResponse
    {
        return $this->success($this->evaluationService->getEvaluations($result->id, $version));
    }
}
```

- [ ] **Step 2: Add routes**

In `routes/api.php`, inside the `dual.auth` group, after the standalone results routes, add:

```php
        // Evaluations
        Route::post('results/{result}/evaluate', [EvaluationController::class, 'evaluate']);
        Route::get('results/{result}/evaluations', [EvaluationController::class, 'index']);
        Route::get('results/{result}/evaluations/latest', [EvaluationController::class, 'latest']);
        Route::get('results/{result}/evaluations/{version}', [EvaluationController::class, 'show']);
```

Add the import at top of `routes/api.php`:

```php
use App\Http\Controllers\Api\EvaluationController;
```

- [ ] **Step 3: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/EvaluationController.php routes/api.php
git commit -m "feat: evaluation REST API — evaluate, list versions, latest, show"
```

---

## Task 4: MCP Tools

**Files:**
- Modify: `app/Services/McpToolHandler.php`

- [ ] **Step 1: Add tool definitions**

In `McpToolHandler::getToolDefinitions()`, add after the `list_providers` tool definition:

```php
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
```

- [ ] **Step 2: Add to scope mapping**

In `getRequiredScope()`, add `'evaluate_result'` to `$writeTools` and `'get_evaluations'` to `$readTools`.

- [ ] **Step 3: Add to callTool dispatch**

In `callTool()` match block, add:

```php
            'evaluate_result' => $this->evaluateResult($arguments, $user),
            'get_evaluations' => $this->getEvaluationsForResult($arguments, $user),
```

- [ ] **Step 4: Add implementation methods**

Add after the `listProviders` method:

```php
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
```

- [ ] **Step 5: Update tool count in tests**

In `tests/Feature/McpToolHandlerTest.php`, update `assertCount(18, $tools)` to `assertCount(20, $tools)`.

- [ ] **Step 6: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Services/McpToolHandler.php tests/Feature/McpToolHandlerTest.php
git commit -m "feat: evaluate_result and get_evaluations MCP tools"
```

---

## Task 5: Seed Evaluation Command

**Files:**
- Create: `app/Console/Commands/SeedEvaluationCommand.php`

- [ ] **Step 1: Create command**

Create `app/Console/Commands/SeedEvaluationCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\EvaluationSetting;
use App\Models\Prompt;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Console\Command;

class SeedEvaluationCommand extends Command
{
    protected $signature = 'urge:seed-evaluation';
    protected $description = 'Create default evaluation prompt and settings';

    public function handle(): int
    {
        $user = User::first();
        if (!$user) {
            $this->error('No users exist. Register a user first.');
            return 1;
        }

        // Create evaluation prompt if it doesn't exist
        $prompt = Prompt::where('slug', 'system-evaluation-template')->first();
        if (!$prompt) {
            $prompt = Prompt::create([
                'name'        => 'System Evaluation Template',
                'type'        => 'fragment',
                'description' => 'Default evaluation prompt used to assess LLM results. Edit this to customize evaluation criteria.',
                'created_by'  => $user->id,
            ]);

            $content = <<<'TEMPLATE'
Evaluate the following LLM response against the original prompt.

ORIGINAL PROMPT:
---
{{original_prompt}}
---

LLM RESPONSE:
---
{{response_text}}
---

EVALUATION DIMENSIONS:
{{dimensions}}

RATING SCALE: {{scale}}

For each dimension, provide a score (1-5) and brief reasoning.
Return ONLY valid JSON in this exact format:
{"scores":[{"dimension":"dimension_name","score":N,"reasoning":"brief explanation"}]}
TEMPLATE;

            app(VersioningService::class)->createVersion($prompt, [
                'content'        => $content,
                'commit_message' => 'Default evaluation template',
            ], $user);

            $this->info("Created evaluation prompt: {$prompt->slug}");
        } else {
            $this->info("Evaluation prompt already exists: {$prompt->slug}");
        }

        // Set default settings
        EvaluationSetting::set('enabled', false);
        EvaluationSetting::set('auto_evaluate', false);
        EvaluationSetting::set('prompt_slug', $prompt->slug);
        EvaluationSetting::set('dimensions', config('urge.evaluation.default_dimensions'));

        $this->info('Evaluation settings initialized.');
        $this->info('Enable evaluation in Settings > Evaluation tab.');

        return 0;
    }
}
```

- [ ] **Step 2: Run command, then tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan urge:seed-evaluation
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Console/Commands/SeedEvaluationCommand.php
git commit -m "feat: urge:seed-evaluation command for default evaluation prompt and settings"
```

---

## Task 6: Settings UI (Livewire)

**Files:**
- Create: `app/Livewire/Settings/Evaluation.php`
- Create: `resources/views/livewire/settings/evaluation.blade.php`
- Modify: `app/Livewire/Settings.php`
- Modify: `resources/views/livewire/settings.blade.php`

- [ ] **Step 1: Create Evaluation Livewire component**

Create `app/Livewire/Settings/Evaluation.php`:

```php
<?php

namespace App\Livewire\Settings;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Prompt;
use Livewire\Component;

class Evaluation extends Component
{
    public bool $enabled = false;
    public bool $autoEvaluate = false;
    public ?int $defaultProviderId = null;
    public string $promptSlug = '';
    public array $dimensions = [];
    public string $newDimensionName = '';
    public string $newDimensionDescription = '';

    public function mount(): void
    {
        $this->enabled = (bool) EvaluationSetting::get('enabled', false);
        $this->autoEvaluate = (bool) EvaluationSetting::get('auto_evaluate', false);
        $this->defaultProviderId = EvaluationSetting::get('default_provider_id');
        $this->promptSlug = EvaluationSetting::get('prompt_slug', 'system-evaluation-template');
        $this->dimensions = EvaluationSetting::get('dimensions', config('urge.evaluation.default_dimensions'));
    }

    public function save(): void
    {
        EvaluationSetting::set('enabled', $this->enabled);
        EvaluationSetting::set('auto_evaluate', $this->autoEvaluate);
        EvaluationSetting::set('default_provider_id', $this->defaultProviderId);
        EvaluationSetting::set('prompt_slug', $this->promptSlug);
        EvaluationSetting::set('dimensions', $this->dimensions);
        $this->dispatch('notify', message: 'Evaluation settings saved', type: 'success');
    }

    public function toggleDimension(int $index): void
    {
        $this->dimensions[$index]['enabled'] = !$this->dimensions[$index]['enabled'];
    }

    public function updateWeight(int $index, float $weight): void
    {
        $this->dimensions[$index]['weight'] = max(0, min(5, $weight));
    }

    public function addDimension(): void
    {
        if (!$this->newDimensionName) {
            return;
        }

        $this->dimensions[] = [
            'name'        => \Illuminate\Support\Str::slug($this->newDimensionName, '_'),
            'description' => $this->newDimensionDescription ?: $this->newDimensionName,
            'weight'      => 1.0,
            'enabled'     => true,
            'builtin'     => false,
        ];

        $this->newDimensionName = '';
        $this->newDimensionDescription = '';
    }

    public function removeDimension(int $index): void
    {
        if (isset($this->dimensions[$index]) && !($this->dimensions[$index]['builtin'] ?? true)) {
            array_splice($this->dimensions, $index, 1);
        }
    }

    public function render()
    {
        return view('livewire.settings.evaluation', [
            'providers'      => LlmProvider::where('is_active', true)->get(['id', 'name', 'model']),
            'evalPrompts'    => Prompt::where('type', 'fragment')->get(['id', 'slug', 'name']),
        ]);
    }
}
```

- [ ] **Step 2: Create evaluation settings view**

Create `resources/views/livewire/settings/evaluation.blade.php`:

```blade
<div class="space-y-6">
    {{-- Toggles --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Evaluation</h3>
        <label class="flex items-center gap-3">
            <input type="checkbox" wire:model.live="enabled" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
            <span class="text-sm text-gray-700 dark:text-gray-300">Enable evaluation</span>
        </label>
        <label class="flex items-center gap-3">
            <input type="checkbox" wire:model.live="autoEvaluate" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
            <span class="text-sm text-gray-700 dark:text-gray-300">Auto-evaluate after run_prompt</span>
        </label>
    </div>

    {{-- Source --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Evaluation Source</h3>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Evaluation Prompt</label>
            <select wire:model="promptSlug" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm">
                @foreach($evalPrompts as $p)
                    <option value="{{ $p->slug }}">{{ $p->name }} ({{ $p->slug }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Default Evaluator Provider</label>
            <select wire:model="defaultProviderId" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm">
                <option value="">— Select provider —</option>
                @foreach($providers as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->model }})</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Dimensions --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Dimensions</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 dark:text-gray-400">
                    <th class="text-left py-1 w-8">On</th>
                    <th class="text-left py-1">Name</th>
                    <th class="text-left py-1">Description</th>
                    <th class="text-left py-1 w-20">Weight</th>
                    <th class="w-8"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($dimensions as $i => $dim)
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="py-2">
                        <input type="checkbox" wire:click="toggleDimension({{ $i }})" @checked($dim['enabled'])
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                    </td>
                    <td class="py-2 text-gray-700 dark:text-gray-300">{{ $dim['name'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400 text-xs">{{ $dim['description'] }}</td>
                    <td class="py-2">
                        <input type="number" wire:change="updateWeight({{ $i }}, $event.target.value)"
                            value="{{ $dim['weight'] }}" step="0.1" min="0" max="5"
                            class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs">
                    </td>
                    <td class="py-2">
                        @if(!($dim['builtin'] ?? true))
                        <button wire:click="removeDimension({{ $i }})" class="text-red-400 hover:text-red-300 text-xs">&times;</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex items-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
            <input wire:model="newDimensionName" placeholder="Dimension name" class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs px-2 py-1">
            <input wire:model="newDimensionDescription" placeholder="Description" class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs px-2 py-1">
            <button wire:click="addDimension" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded">+ Add</button>
        </div>
    </div>

    <button wire:click="save" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-6 py-2 rounded-lg">
        Save Settings
    </button>
</div>
```

- [ ] **Step 3: Add evaluation tab to Settings**

In `app/Livewire/Settings.php`, add `$tabs[] = 'evaluation';` after the `'templates'` line in `getVisibleTabsProperty()`.

In `resources/views/livewire/settings.blade.php`, add the tab button (before the users button):

```blade
@if(in_array('evaluation', $this->visibleTabs))
<button wire:click="$set('activeTab', 'evaluation')"
        class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'evaluation' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
    Evaluation
</button>
@endif
```

And add the tab content (before the users elseif):

```blade
@elseif($activeTab === 'evaluation' && in_array('evaluation', $this->visibleTabs))
    <livewire:settings.evaluation />
```

- [ ] **Step 4: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Livewire/Settings/Evaluation.php resources/views/livewire/settings/evaluation.blade.php app/Livewire/Settings.php resources/views/livewire/settings.blade.php
git commit -m "feat: Evaluation settings tab — toggle, provider, prompt, dimensions config"
```

---

## Task 7: Canvas + Workspace UI

**Files:**
- Modify: `app/Http/Controllers/Api/GraphController.php`
- Modify: `resources/js/spa/components/canvas/nodes/PromptNode.jsx`
- Modify: `resources/js/spa/components/workspace/ResultsPanel.jsx`

- [ ] **Step 1: Add evaluation data to graph nodes**

In `GraphController::nodes()`, add to the prompt data mapping (inside the `$promptsData` closure), after `'results_count'`:

```php
            'avg_evaluation_score' => \App\Models\ResultEvaluation::whereIn(
                'result_id', $prompt->results()->pluck('id')
            )->where('evaluation_version', function ($q) use ($prompt) {
                $q->selectRaw('MAX(evaluation_version)')
                  ->from('result_evaluations')
                  ->whereColumn('result_id', 'result_evaluations.result_id');
            })->avg('score'),
```

Note: This is a simple approach. If performance is a concern with many results, it can be optimized later with a subquery or cached value.

- [ ] **Step 2: Add score badge to PromptNode**

In `resources/js/spa/components/canvas/nodes/PromptNode.jsx`, add after the results count line:

```jsx
                    {data.avg_evaluation_score && (
                        <span className={`${
                            data.avg_evaluation_score >= 4 ? 'text-green-400' :
                            data.avg_evaluation_score >= 3 ? 'text-amber-400' : 'text-red-400'
                        }`}>
                            ⭐ {Number(data.avg_evaluation_score).toFixed(1)}
                        </span>
                    )}
```

- [ ] **Step 3: Add evaluation to ResultsPanel**

In `resources/js/spa/components/workspace/ResultsPanel.jsx`, add an evaluation score badge on each result card. After the star rating display, add:

```jsx
{r.evaluations_latest?.composite_score && (
    <span className={`text-[10px] px-1.5 py-0.5 rounded ${
        r.evaluations_latest.composite_score >= 4 ? 'bg-green-900/30 text-green-400' :
        r.evaluations_latest.composite_score >= 3 ? 'bg-amber-900/30 text-amber-400' :
        'bg-red-900/30 text-red-400'
    }`}>
        Eval: {r.evaluations_latest.composite_score}
    </span>
)}
```

Note: The results API needs to include evaluation data. Add `evaluations_latest` to the Result API response by extending the ResultController's index/show methods to eager-load and compute composite scores. This is a small addition to the existing controller — load latest evaluation scores per result.

- [ ] **Step 4: Build and commit**

```bash
npm run build
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/GraphController.php resources/js/spa/components/canvas/nodes/PromptNode.jsx resources/js/spa/components/workspace/ResultsPanel.jsx public/build/
git commit -m "feat: evaluation scores on canvas prompt nodes and workspace result cards"
```

---

## Task 8: Tests

**Files:**
- Create: `tests/Feature/EvaluationTest.php`

- [ ] **Step 1: Create evaluation tests**

Create `tests/Feature/EvaluationTest.php` with tests covering:

1. `test_seed_evaluation_creates_prompt_and_settings` — run artisan command, verify prompt + settings exist
2. `test_evaluate_result_via_api` — POST `/results/{id}/evaluate` returns scores (requires mock or real LLM provider)
3. `test_list_evaluation_versions` — GET `/results/{id}/evaluations` returns version list
4. `test_get_latest_evaluation` — GET `/results/{id}/evaluations/latest`
5. `test_get_specific_evaluation_version` — GET `/results/{id}/evaluations/1`
6. `test_evaluation_disabled_returns_error` — when evaluation not enabled
7. `test_human_rating_syncs_to_evaluation` — star-rate a result, verify human dimension appears
8. `test_composite_score_calculation` — insert evaluation rows, verify weighted average
9. `test_mcp_evaluate_result_tool` — MCP tools/call with evaluate_result
10. `test_mcp_get_evaluations_tool` — MCP tools/call with get_evaluations

For tests that need LLM dispatch, mock `LlmDispatchService::dispatchWithSystem()` to return a fake `LlmResult` with JSON scores.

- [ ] **Step 2: Run full test suite, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add tests/Feature/EvaluationTest.php
git commit -m "test: evaluation feature — API, MCP, scoring, human rating sync"
```

---

## Task 9: Integration + Verify

- [ ] **Step 1: Build**

```bash
npm run build
```

- [ ] **Step 2: Run full test suite**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 365+ existing tests + ~10 new evaluation tests pass.

- [ ] **Step 3: Manual verification**

1. `php artisan urge:seed-evaluation` — creates default prompt + settings
2. Settings > Evaluation — enable, select provider, verify dimensions table
3. MCP `evaluate_result` with a result ID → returns scores
4. MCP `get_evaluations` → returns scores
5. API `POST /results/{id}/evaluate` → 201 with scores
6. API `GET /results/{id}/evaluations/latest` → scores
7. Star-rate a result → human dimension appears in evaluations
8. Re-evaluate same result → evaluation_version increments
9. Canvas → prompt nodes show score badge
10. Workspace → result cards show evaluation score

- [ ] **Step 4: Commit and push**

```bash
git add -A
git commit -m "feat: Result evaluation complete — LLM scoring, API, MCP, settings, canvas"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Seed command | `urge:seed-evaluation` creates prompt + settings |
| Settings UI | Evaluation tab with toggles, provider, dimensions |
| Evaluate via API | POST `/results/{id}/evaluate` → 201 + scores |
| List versions | GET `/results/{id}/evaluations` → version list |
| Latest evaluation | GET `/results/{id}/evaluations/latest` → scores + composite |
| MCP evaluate | `evaluate_result` tool → scores |
| MCP get evals | `get_evaluations` tool → scores |
| Human rating | Star-rate → human dimension in evaluation |
| Re-evaluation | Second evaluate → version 2 with new scores |
| Auto-evaluate | Enable in settings → `run_prompt` includes evaluation |
| Canvas badge | Prompt nodes show color-coded score |
| Workspace | Result cards show evaluation composite |
| Composite score | Weighted average of all dimensions |
| Custom dimensions | Add via settings → included in next evaluation |
