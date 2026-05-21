<?php

return [
    // Template rendering safety budgets (PB-3 / TPL-04)
    'max_include_depth'      => (int) env('URGE_MAX_INCLUDE_DEPTH', 10),
    // Total number of include expansions allowed in a single render —
    // bounds sibling-fanout amplification (Nⁿ / "billion laughs").
    'max_include_expansions' => (int) env('URGE_MAX_INCLUDE_EXPANSIONS', 500),
    // Total rendered output size budget in bytes.
    'max_render_bytes'       => (int) env('URGE_MAX_RENDER_BYTES', 5 * 1024 * 1024),
    // Max combined size (system+user) of an LLM-dispatched prompt — PB-4
    // pre-dispatch guard that bounds the workload of one HTTP request even
    // if includes inflate to the size budget (LLM-07).
    'max_prompt_bytes'       => (int) env('URGE_MAX_PROMPT_BYTES', 1024 * 1024),

    'curl_ssl_verify'    => env('CURL_SSL_VERIFY', true),

    // API Key settings
    'key_prefix'         => env('URGE_KEY_PREFIX', 'urge_'),
    'key_bytes'          => (int) env('URGE_KEY_BYTES', 31),
    'key_preview_length' => (int) env('URGE_KEY_PREVIEW_LENGTH', 8),

    // API Rate limiting (per key)
    'api_rate_limit'     => (int) env('URGE_API_RATE_LIMIT', 60),
    'api_rate_window'    => (int) env('URGE_API_RATE_WINDOW', 60),

    // Collection nesting
    'max_collection_depth'       => (int) env('URGE_MAX_COLLECTION_DEPTH', 5),
    'unlimited_collection_depth' => (bool) env('URGE_UNLIMITED_COLLECTION_DEPTH', false),

    // OAuth 2.1
    'oauth' => [
        // Access tokens are bearer credentials; keep them short-lived and
        // rely on refresh-token rotation for long sessions (AUTH-10).
        'token_ttl'         => (int) env('OAUTH_TOKEN_TTL', 3600), // 1 hour
        'refresh_token_ttl' => (int) env('OAUTH_REFRESH_TOKEN_TTL', 2592000), // 30 days
        'code_ttl'          => 600,
        'scopes'            => ['mcp:read', 'mcp:write', 'mcp:admin'],
    ],

    // GitHub OAuth (external provider)
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
    ],

    // Evaluation
    'evaluation' => [
        'default_dimensions' => [
            ['name' => 'relevance', 'description' => 'Does the response address what the prompt asked for?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'completeness', 'description' => 'Are all parts of the prompt addressed? Nothing missing?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'accuracy', 'description' => 'Is the information correct and well-reasoned?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'clarity', 'description' => 'Is the response well-structured and easy to follow?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'conciseness', 'description' => 'Right amount of detail — not too verbose, not too sparse?', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
            ['name' => 'human', 'description' => 'Human star-rating of the result.', 'weight' => 1.0, 'enabled' => true, 'builtin' => true],
        ],
    ],
];
