<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineTemplateChannel extends Model
{
    protected $fillable = [
        'pipeline_template_id',
        'role_label',
        'llm_provider_id',
        'system_prompt',
        'trigger',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(PipelineTemplate::class, 'pipeline_template_id');
    }

    public function llmProvider()
    {
        return $this->belongsTo(LlmProvider::class);
    }
}
