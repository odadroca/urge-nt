<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\PipelineTemplateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\GraphController;
use App\Http\Controllers\Api\ShareLinkController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PromptController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    // SPA auth endpoints (Sanctum session-based)
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);
    });

    Route::middleware('dual.auth')->group(function () {
        // Prompts (namespaced)
        Route::get('prompts', [PromptController::class, 'index']);
        Route::post('prompts', [PromptController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}', [PromptController::class, 'show']);
        Route::patch('prompts/{username}/{promptSlug}', [PromptController::class, 'update']);
        Route::delete('prompts/{username}/{promptSlug}', [PromptController::class, 'destroy']);

        // Versions (namespaced)
        Route::get('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}/versions/{version}', [VersionController::class, 'show']);

        // Branches (namespaced)
        Route::get('prompts/{username}/{promptSlug}/branches', [BranchController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/branches', [BranchController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}/branches/{branch}', [BranchController::class, 'show']);
        Route::delete('prompts/{username}/{promptSlug}/branches/{branch}', [BranchController::class, 'destroy']);
        Route::patch('prompts/{username}/{promptSlug}/branches/{branch}/default', [BranchController::class, 'setDefault']);

        // Render (namespaced)
        Route::post('prompts/{username}/{promptSlug}/render', [RenderController::class, 'render']);

        // Results (prompt-scoped, namespaced)
        Route::get('prompts/{username}/{promptSlug}/results', [ResultController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/results', [ResultController::class, 'store']);

        // Results (standalone)
        Route::get('results/starred', [ResultController::class, 'starred']);
        Route::get('results/{result}', [ResultController::class, 'show']);
        Route::patch('results/{result}', [ResultController::class, 'update']);
        Route::delete('results/{result}', [ResultController::class, 'destroy']);

        // Evaluations
        Route::post('results/{result}/evaluate', [EvaluationController::class, 'evaluate']);
        Route::get('results/{result}/evaluations', [EvaluationController::class, 'index']);
        Route::get('results/{result}/evaluations/latest', [EvaluationController::class, 'latest']);
        Route::get('results/{result}/evaluations/{version}', [EvaluationController::class, 'show']);

        // Legacy redirect (single slug without slash)
        Route::get('prompts/{slug}', [PromptController::class, 'legacyRedirect'])->where('slug', '[^/]+');

        // Sharing
        Route::post('prompts/{username}/{promptSlug}/share', [PromptController::class, 'share']);
        Route::delete('prompts/{username}/{promptSlug}/share/{team}', [PromptController::class, 'unshare']);

        // Teams
        Route::get('teams', [TeamController::class, 'index']);
        Route::post('teams', [TeamController::class, 'store']);
        Route::get('teams/{team:slug}', [TeamController::class, 'show']);
        Route::patch('teams/{team:slug}', [TeamController::class, 'update']);
        Route::delete('teams/{team:slug}', [TeamController::class, 'destroy']);
        Route::post('teams/{team:slug}/members', [TeamController::class, 'addMember']);
        Route::delete('teams/{team:slug}/members/{user}', [TeamController::class, 'removeMember']);

        // Collections
        Route::get('collections', [CollectionController::class, 'index']);
        Route::post('collections', [CollectionController::class, 'store']);
        Route::get('collections/{collection:slug}', [CollectionController::class, 'show']);
        Route::patch('collections/{collection:slug}', [CollectionController::class, 'update']);
        Route::delete('collections/{collection:slug}', [CollectionController::class, 'destroy']);
        Route::post('collections/{collection:slug}/items', [CollectionController::class, 'addItem']);
        Route::delete('collections/{collection:slug}/items/{item}', [CollectionController::class, 'removeItem']);

        // Pipeline Templates
        Route::get('pipeline-templates', [PipelineTemplateController::class, 'index']);
        Route::post('pipeline-templates', [PipelineTemplateController::class, 'store']);
        Route::get('pipeline-templates/{pipelineTemplate:slug}', [PipelineTemplateController::class, 'show']);
        Route::patch('pipeline-templates/{pipelineTemplate:slug}', [PipelineTemplateController::class, 'update']);
        Route::delete('pipeline-templates/{pipelineTemplate:slug}', [PipelineTemplateController::class, 'destroy']);
        Route::post('pipeline-templates/{pipelineTemplate:slug}/channels', [PipelineTemplateController::class, 'addChannel']);
        Route::patch('pipeline-templates/{pipelineTemplate:slug}/channels/{channel}', [PipelineTemplateController::class, 'updateChannel']);
        Route::delete('pipeline-templates/{pipelineTemplate:slug}/channels/{channel}', [PipelineTemplateController::class, 'removeChannel']);
        Route::post('prompts/{username}/{promptSlug}/run-template', [PipelineTemplateController::class, 'runTemplate']);

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);

        // Share Links
        Route::get('collections/{collection:slug}/share-links', [ShareLinkController::class, 'index']);
        Route::post('collections/{collection:slug}/share-links', [ShareLinkController::class, 'store']);
        Route::delete('collections/{collection:slug}/share-links/{shareLink}', [ShareLinkController::class, 'destroy']);

        // Graph
        Route::get('graph/nodes', [GraphController::class, 'nodes']);
        Route::post('graph/positions', [GraphController::class, 'positions']);
        Route::get('graph/edges', [GraphController::class, 'edges']);
        Route::post('prompts/{username}/{promptSlug}/append-include', [GraphController::class, 'appendInclude']);
        Route::delete('prompts/{username}/{promptSlug}/remove-include', [GraphController::class, 'removeInclude']);
    });

    // MCP — auth handled internally for OAuth 2.1 discovery flow
    Route::post('mcp', [McpController::class, 'handle']);
    Route::get('mcp', [McpController::class, 'stream']);
    Route::delete('mcp', [McpController::class, 'destroy']);
});

// Serve OpenAPI spec
Route::get('openapi.json', function () {
    $path = public_path('openapi.json');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'application/json']);
});
