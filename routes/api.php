<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\EvaluationSettingsController;
use App\Http\Controllers\Api\GraphController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LlmProviderController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PromptController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\ShareLinkController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
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

        // Current-user profile (SPA self-service)
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::delete('profile', [ProfileController::class, 'destroy']);
    });

    Route::middleware('dual.auth')->group(function () {
        // Prompts (namespaced)
        Route::get('prompts', [PromptController::class, 'index']);
        Route::post('prompts', [PromptController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}', [PromptController::class, 'show']);
        Route::patch('prompts/{username}/{promptSlug}', [PromptController::class, 'update']);
        Route::delete('prompts/{username}/{promptSlug}', [PromptController::class, 'destroy']);
        Route::patch('prompts/{username}/{promptSlug}/pin', [PromptController::class, 'pin']);

        // Versions (namespaced)
        Route::get('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}/versions/{version}', [VersionController::class, 'show']);
        Route::get('prompts/{username}/{promptSlug}/versions/{version}/download', [VersionController::class, 'download']);
        Route::patch('prompts/{username}/{promptSlug}/versions/{version}/archive', [VersionController::class, 'archive']);

        // Branches (namespaced)
        Route::get('prompts/{username}/{promptSlug}/branches', [BranchController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/branches', [BranchController::class, 'store']);
        Route::get('prompts/{username}/{promptSlug}/branches/{branch}', [BranchController::class, 'show']);
        Route::delete('prompts/{username}/{promptSlug}/branches/{branch}', [BranchController::class, 'destroy']);
        Route::patch('prompts/{username}/{promptSlug}/branches/{branch}/default', [BranchController::class, 'setDefault']);

        // Render (namespaced)
        Route::post('prompts/{username}/{promptSlug}/render', [RenderController::class, 'render']);

        // LLM-dispatching endpoints are throttled per authenticated user
        // (LLM-06) — previously only API-key callers were rate-limited;
        // Sanctum/OAuth callers were unbounded.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('prompts/{username}/{promptSlug}/run', [PromptController::class, 'run']);
            Route::post('prompts/{username}/{promptSlug}/run-pipeline', [PipelineController::class, 'runPipeline']);
            Route::post('results/{result}/evaluate', [EvaluationController::class, 'evaluate']);
        });

        // Results (prompt-scoped, namespaced)
        Route::get('prompts/{username}/{promptSlug}/results', [ResultController::class, 'index']);
        Route::post('prompts/{username}/{promptSlug}/results', [ResultController::class, 'store']);

        // Results (standalone)
        Route::get('results/starred', [ResultController::class, 'starred']);
        Route::get('results/{result}', [ResultController::class, 'show']);
        Route::get('results/{result}/download', [ResultController::class, 'download']);
        Route::patch('results/{result}', [ResultController::class, 'update']);
        Route::delete('results/{result}', [ResultController::class, 'destroy']);

        // Evaluations (evaluate is rate-limited above)
        Route::get('results/{result}/evaluations', [EvaluationController::class, 'index']);
        Route::get('results/{result}/evaluations/latest', [EvaluationController::class, 'latest']);
        Route::get('results/{result}/evaluations/{version}', [EvaluationController::class, 'show']);

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
        Route::post('teams/{team:slug}/leave', [TeamController::class, 'leave']);

        // Collections
        Route::get('collections', [CollectionController::class, 'index']);
        Route::post('collections', [CollectionController::class, 'store']);
        Route::get('collections/{collection:slug}', [CollectionController::class, 'show']);
        Route::patch('collections/{collection:slug}', [CollectionController::class, 'update']);
        Route::delete('collections/{collection:slug}', [CollectionController::class, 'destroy']);
        Route::post('collections/{collection:slug}/items', [CollectionController::class, 'addItem']);
        Route::delete('collections/{collection:slug}/items/{item}', [CollectionController::class, 'removeItem']);

        // Pipelines
        Route::get('pipelines', [PipelineController::class, 'index']);
        Route::post('pipelines', [PipelineController::class, 'store']);
        Route::get('pipelines/{pipeline:slug}', [PipelineController::class, 'show']);
        Route::patch('pipelines/{pipeline:slug}', [PipelineController::class, 'update']);
        Route::delete('pipelines/{pipeline:slug}', [PipelineController::class, 'destroy']);
        Route::post('pipelines/{pipeline:slug}/channels', [PipelineController::class, 'addChannel']);
        Route::patch('pipelines/{pipeline:slug}/channels/{channel}', [PipelineController::class, 'updateChannel']);
        Route::delete('pipelines/{pipeline:slug}/channels/{channel}', [PipelineController::class, 'removeChannel']);
        // run-pipeline is rate-limited above (LLM-06)

        // LLM Providers (read is non-admin so the SPA can pick a provider; mutations are admin-only)
        Route::get('providers', [LlmProviderController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::post('providers', [LlmProviderController::class, 'store']);
            Route::patch('providers/{id}', [LlmProviderController::class, 'update']);
            Route::delete('providers/{id}', [LlmProviderController::class, 'destroy']);
            Route::post('providers/{id}/test', [LlmProviderController::class, 'test']);
        });

        // Run prompt with LLM
        // run is rate-limited above (LLM-06)

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::patch('categories/{id}', [CategoryController::class, 'update']);
        Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

        // Share Links
        Route::get('collections/{collection:slug}/share-links', [ShareLinkController::class, 'index']);
        Route::post('collections/{collection:slug}/share-links', [ShareLinkController::class, 'store']);
        Route::delete('collections/{collection:slug}/share-links/{shareLink}', [ShareLinkController::class, 'destroy']);

        // API Keys
        Route::get('api-keys', [ApiKeyController::class, 'index']);
        Route::post('api-keys', [ApiKeyController::class, 'store']);
        Route::patch('api-keys/{id}', [ApiKeyController::class, 'update']);
        Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy']);

        // Evaluation Settings
        Route::get('evaluation-settings', [EvaluationSettingsController::class, 'show']);
        Route::patch('evaluation-settings', [EvaluationSettingsController::class, 'update']);

        // Users (admin-only)
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::patch('users/{id}', [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);
        });

        // Graph
        Route::get('graph/nodes', [GraphController::class, 'nodes']);
        Route::post('graph/positions', [GraphController::class, 'positions']);
        Route::get('graph/edges', [GraphController::class, 'edges']);
        Route::post('prompts/{username}/{promptSlug}/append-include', [GraphController::class, 'appendInclude']);
        Route::delete('prompts/{username}/{promptSlug}/remove-include', [GraphController::class, 'removeInclude']);
    });

    // MCP — auth handled internally for OAuth 2.1 discovery flow
    Route::post('mcp', [McpController::class, 'handle']);
    Route::delete('mcp', [McpController::class, 'destroy']);
});
