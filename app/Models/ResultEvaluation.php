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
            if (! $latestVersion) {
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
