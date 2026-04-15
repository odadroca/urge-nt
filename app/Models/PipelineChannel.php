<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineChannel extends Model
{
    protected $table = 'pipeline_channels';

    protected $fillable = [
        'pipeline_id',
        'role_label',
        'llm_provider_id',
        'system_prompt',
        'trigger',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function llmProvider()
    {
        return $this->belongsTo(LlmProvider::class);
    }
}
