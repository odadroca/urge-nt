<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'prompt_id',
        'prompt_version_id',
        'source',
        'run_source',
        'role_label',
        'pipeline_id',
        'pipeline_run_id',
        'provider_name',
        'model_name',
        'llm_provider_id',
        'rendered_content',
        'variables_used',
        'response_text',
        'response_hash',
        'notes',
        'rating',
        'starred',
        'input_tokens',
        'output_tokens',
        'duration_ms',
        'status',
        'error_message',
        'import_filename',
        'created_by',
    ];

    /**
     * LLM-05: prompt/result content and metadata is now encrypted at
     * rest. Columns that the audit identified as plaintext-sensitive:
     *  - response_text       (LLM response body)
     *  - rendered_content    (the variable-substituted prompt)
     *  - error_message       (may contain echoed upstream body / creds)
     *  - variables_used      (may contain PII the user pasted)
     *
     * The `encrypted` cast uses Laravel's Crypt facade (AES-256-GCM
     * under the hood); ciphertext varies per write (random IV), so
     * equality SQL lookups no longer work — see response_hash for
     * the dedup path used by ImportV1Command.
     */
    protected $casts = [
        'variables_used' => 'encrypted:array',
        'response_text' => 'encrypted',
        'rendered_content' => 'encrypted',
        'error_message' => 'encrypted',
        'starred' => 'boolean',
        'rating' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'duration_ms' => 'integer',
        'pipeline_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Result $result) {
            // Maintain a non-secret sha256 of response_text so dedup
            // queries still work over the encrypted column.
            if ($result->isDirty('response_text')) {
                $plain = $result->response_text;
                $result->response_hash = $plain !== null ? hash('sha256', $plain) : null;
            }
        });
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }

    public function promptVersion()
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function llmProvider()
    {
        return $this->belongsTo(LlmProvider::class);
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected $appends = ['evaluation_score'];

    public function getEvaluationScoreAttribute(): ?float
    {
        return ResultEvaluation::compositeScore($this->id);
    }
}
