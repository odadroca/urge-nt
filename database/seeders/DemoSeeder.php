<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::create(['name' => 'Admin', 'email' => 'admin@urge.local', 'password' => bcrypt('password')]);
        }

        // Categories
        $catDev = Category::firstOrCreate(['name' => 'Development'], ['color' => '#3b82f6']);
        $catWrite = Category::firstOrCreate(['name' => 'Writing'], ['color' => '#8b5cf6']);
        $catData = Category::firstOrCreate(['name' => 'Data Analysis'], ['color' => '#10b981']);

        // Fragment: System Context
        $sysCtx = Prompt::firstOrCreate(['slug' => 'system-context'], [
            'name' => 'System Context',
            'type' => 'fragment',
            'description' => 'Reusable system instruction fragment',
            'category_id' => $catDev->id,
            'tags' => ['system', 'reusable'],
            'created_by' => $user->id,
        ]);
        if ($sysCtx->wasRecentlyCreated) {
            PromptVersion::create([
                'prompt_id' => $sysCtx->id,
                'version_number' => 1,
                'content' => 'You are a senior {{role}} with deep expertise in {{domain}}. You write clean, production-ready code and explain your reasoning clearly.',
                'commit_message' => 'Initial system context fragment',
                'variables' => ['role', 'domain'],
                'variable_metadata' => [
                    'role' => ['type' => 'string', 'default' => 'software engineer', 'description' => 'The AI role'],
                    'domain' => ['type' => 'string', 'default' => 'web development', 'description' => 'Area of expertise'],
                ],
                'created_by' => $user->id,
            ]);
        }

        // Fragment: Output Format
        $outFmt = Prompt::firstOrCreate(['slug' => 'output-format'], [
            'name' => 'Output Format',
            'type' => 'fragment',
            'description' => 'Standard output formatting instructions',
            'category_id' => $catWrite->id,
            'tags' => ['format', 'reusable'],
            'created_by' => $user->id,
        ]);
        if ($outFmt->wasRecentlyCreated) {
            PromptVersion::create([
                'prompt_id' => $outFmt->id,
                'version_number' => 1,
                'content' => "Format your response as follows:\n- Use clear headings with markdown\n- Include code examples where relevant\n- Keep explanations concise but thorough\n- End with a summary of key points",
                'commit_message' => 'Initial output format fragment',
                'variables' => [],
                'created_by' => $user->id,
            ]);
        }

        // Prompt: Code Review
        $codeReview = Prompt::firstOrCreate(['slug' => 'code-review'], [
            'name' => 'Code Review Assistant',
            'type' => 'prompt',
            'description' => 'Reviews code for bugs, performance, and best practices',
            'category_id' => $catDev->id,
            'tags' => ['code', 'review', 'quality'],
            'created_by' => $user->id,
        ]);
        if ($codeReview->wasRecentlyCreated) {
            PromptVersion::create([
                'prompt_id' => $codeReview->id,
                'version_number' => 1,
                'content' => "{{>system-context}}\n\nReview the following {{language}} code for:\n1. Bugs and potential errors\n2. Performance issues\n3. Security vulnerabilities\n4. Code style and best practices\n\nCode to review:\n```{{language}}\n{{code}}\n```\n\n{{>output-format}}",
                'commit_message' => 'Initial code review prompt with includes',
                'variables' => ['language', 'code'],
                'includes' => ['system-context', 'output-format'],
                'variable_metadata' => [
                    'language' => ['type' => 'enum', 'default' => 'php', 'description' => 'Programming language', 'options' => ['php', 'javascript', 'python', 'go', 'rust']],
                    'code' => ['type' => 'text', 'default' => '', 'description' => 'The code to review'],
                ],
                'created_by' => $user->id,
            ]);
            PromptVersion::create([
                'prompt_id' => $codeReview->id,
                'version_number' => 2,
                'content' => "{{>system-context}}\n\nReview the following {{language}} code. Focus on: {{focus_areas}}\n\nCode to review:\n```{{language}}\n{{code}}\n```\n\nProvide a severity rating (low/medium/high/critical) for each issue found.\n\n{{>output-format}}",
                'commit_message' => 'Add focus areas and severity ratings',
                'variables' => ['language', 'code', 'focus_areas'],
                'includes' => ['system-context', 'output-format'],
                'variable_metadata' => [
                    'language' => ['type' => 'enum', 'default' => 'php', 'description' => 'Programming language', 'options' => ['php', 'javascript', 'python', 'go', 'rust']],
                    'code' => ['type' => 'text', 'default' => '', 'description' => 'The code to review'],
                    'focus_areas' => ['type' => 'string', 'default' => 'bugs, performance, security', 'description' => 'Comma-separated areas to focus on'],
                ],
                'created_by' => $user->id,
            ]);
        }

        // Prompt: Blog Post Writer
        $blog = Prompt::firstOrCreate(['slug' => 'blog-post-writer'], [
            'name' => 'Blog Post Writer',
            'type' => 'prompt',
            'description' => 'Generates structured blog posts on any topic',
            'category_id' => $catWrite->id,
            'tags' => ['writing', 'blog', 'content'],
            'created_by' => $user->id,
        ]);
        if ($blog->wasRecentlyCreated) {
            PromptVersion::create([
                'prompt_id' => $blog->id,
                'version_number' => 1,
                'content' => "You are an expert content writer specializing in {{niche}}.\n\nWrite a blog post about: {{topic}}\n\nTarget audience: {{audience}}\nTone: {{tone}}\nWord count: approximately {{word_count}} words\n\nInclude:\n- An engaging hook in the introduction\n- 3-5 main sections with subheadings\n- Practical examples or actionable tips\n- A compelling conclusion with a call to action",
                'commit_message' => 'Initial blog post writer',
                'variables' => ['niche', 'topic', 'audience', 'tone', 'word_count'],
                'variable_metadata' => [
                    'niche' => ['type' => 'string', 'default' => 'technology', 'description' => 'Blog niche or industry'],
                    'topic' => ['type' => 'text', 'default' => '', 'description' => 'The blog post topic'],
                    'audience' => ['type' => 'string', 'default' => 'developers', 'description' => 'Target reader'],
                    'tone' => ['type' => 'enum', 'default' => 'professional', 'description' => 'Writing tone', 'options' => ['casual', 'professional', 'academic', 'conversational']],
                    'word_count' => ['type' => 'number', 'default' => '1500', 'description' => 'Approximate word count'],
                ],
                'created_by' => $user->id,
            ]);
        }

        // Prompt: Data Analysis
        $data = Prompt::firstOrCreate(['slug' => 'data-analysis'], [
            'name' => 'Data Analysis Helper',
            'type' => 'prompt',
            'description' => 'Analyzes datasets and generates insights',
            'category_id' => $catData->id,
            'tags' => ['data', 'analysis', 'insights'],
            'created_by' => $user->id,
        ]);
        if ($data->wasRecentlyCreated) {
            PromptVersion::create([
                'prompt_id' => $data->id,
                'version_number' => 1,
                'content' => "{{>system-context}}\n\nAnalyze the following {{data_format}} data:\n\n{{data}}\n\nProvide:\n1. Summary statistics\n2. Key patterns and trends\n3. Anomalies or outliers\n4. Actionable recommendations\n\nVisualization suggestions: {{include_viz}}",
                'commit_message' => 'Initial data analysis prompt',
                'variables' => ['data_format', 'data', 'include_viz'],
                'includes' => ['system-context'],
                'variable_metadata' => [
                    'data_format' => ['type' => 'enum', 'default' => 'CSV', 'description' => 'Format of input data', 'options' => ['CSV', 'JSON', 'SQL output', 'plain text']],
                    'data' => ['type' => 'text', 'default' => '', 'description' => 'The data to analyze'],
                    'include_viz' => ['type' => 'boolean', 'default' => 'yes', 'description' => 'Include chart/visualization suggestions'],
                ],
                'created_by' => $user->id,
            ]);
        }

        $this->command->info('Seeded: ' . Prompt::count() . ' prompts, ' . PromptVersion::count() . ' versions, ' . Category::count() . ' categories');
    }
}
