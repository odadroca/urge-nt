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
        'prompt_fragment_id',
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

    public function fragment()
    {
        return $this->belongsTo(Prompt::class, 'prompt_fragment_id');
    }

    /**
     * "server" if a configured + active LLM provider can dispatch this channel.
     * "client" if the caller (the LLM client) must run this channel locally
     * because no provider is configured or the configured provider is inactive.
     */
    public function getExecutionModeAttribute(): string
    {
        if (!$this->llm_provider_id) {
            return 'client';
        }
        $provider = $this->llmProvider;
        return $provider && $provider->is_active ? 'server' : 'client';
    }
}
