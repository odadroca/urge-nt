<?php

namespace Database\Seeders;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateChannel;
use Illuminate\Database\Seeder;

class PipelineTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (PipelineTemplate::exists()) {
            return;
        }

        $userId = \App\Models\User::first()?->id ?? 1;

        // SWOT Analysis
        $swot = PipelineTemplate::create([
            'name' => 'SWOT Analysis',
            'description' => 'Dispatches 4 parallel role channels (strengths, weaknesses, opportunities, threats) then synthesises a summary.',
            'created_by' => $userId,
        ]);

        $swotChannels = [
            ['role_label' => 'strengths',     'trigger' => 'parallel',   'sort_order' => 0, 'system_prompt' => 'Analyse the strengths of the following. Be specific and provide concrete examples.'],
            ['role_label' => 'weaknesses',    'trigger' => 'parallel',   'sort_order' => 1, 'system_prompt' => 'Analyse the weaknesses of the following. Be specific and provide concrete examples.'],
            ['role_label' => 'opportunities', 'trigger' => 'parallel',   'sort_order' => 2, 'system_prompt' => 'Analyse the opportunities for the following. Be specific and provide concrete examples.'],
            ['role_label' => 'threats',       'trigger' => 'parallel',   'sort_order' => 3, 'system_prompt' => 'Analyse the threats facing the following. Be specific and provide concrete examples.'],
            ['role_label' => 'summary',       'trigger' => 'synthesis',  'sort_order' => 4, 'system_prompt' => 'Given the four SWOT analyses above, produce a concise integrated summary highlighting the most critical findings and recommended actions.'],
        ];

        foreach ($swotChannels as $ch) {
            PipelineTemplateChannel::create(array_merge($ch, [
                'pipeline_template_id' => $swot->id,
                'llm_provider_id' => null,
            ]));
        }

        // Go-to-Market Review
        $gtm = PipelineTemplate::create([
            'name' => 'Go-to-Market Review',
            'description' => 'Dispatches 3 parallel channels (target market, channels, differentiation) then synthesises a go-to-market overview.',
            'created_by' => $userId,
        ]);

        $gtmChannels = [
            ['role_label' => 'target_market',    'trigger' => 'parallel',   'sort_order' => 0, 'system_prompt' => 'Identify and describe the primary target market for the following. Include demographics, psychographics, and market size estimates.'],
            ['role_label' => 'channels',         'trigger' => 'parallel',   'sort_order' => 1, 'system_prompt' => 'Recommend the most effective distribution and marketing channels for the following. Prioritise by expected ROI.'],
            ['role_label' => 'differentiation',  'trigger' => 'parallel',   'sort_order' => 2, 'system_prompt' => 'Describe the key differentiation and competitive advantages of the following. Compare against likely competitors.'],
            ['role_label' => 'summary',          'trigger' => 'synthesis',  'sort_order' => 3, 'system_prompt' => 'Given the three analyses above, produce a go-to-market overview with clear next steps and priorities.'],
        ];

        foreach ($gtmChannels as $ch) {
            PipelineTemplateChannel::create(array_merge($ch, [
                'pipeline_template_id' => $gtm->id,
                'llm_provider_id' => null,
            ]));
        }
    }
}
