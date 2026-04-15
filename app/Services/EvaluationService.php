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
