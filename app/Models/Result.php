<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'prompt_id',
        'prompt_version_id',
        'source',
        'role_label',
        'pipeline_template_id',
        'pipeline_run_id',
        'provider_name',
        'model_name',
        'llm_provider_id',
        'rendered_content',
        'variables_used',
        'response_text',
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

    protected $casts = [
        'variables_used' => 'array',
        'starred' => 'boolean',
        'rating' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'duration_ms' => 'integer',
        'pipeline_template_id' => 'integer',
    ];

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

    public function pipelineTemplate()
    {
        return $this->belongsTo(PipelineTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
