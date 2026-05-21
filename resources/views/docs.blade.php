<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>URGE API Documentation</title>
    <style>
        .light-mode {
            --scalar-background-1: #f8fafc;
            --scalar-background-2: #f1f5f9;
            --scalar-background-3: #e2e8f0;
            --scalar-color-1: #0f172a;
            --scalar-color-2: #334155;
            --scalar-color-3: #64748b;
            --scalar-color-accent: #4f46e5;
        }
        .dark-mode {
            --scalar-background-1: #0f172a;
            --scalar-background-2: #1e293b;
            --scalar-background-3: #334155;
            --scalar-color-1: #f1f5f9;
            --scalar-color-2: #cbd5e1;
            --scalar-color-3: #94a3b8;
            --scalar-color-accent: #6366f1;
        }
    </style>
</head>
<body>
    <div id="api-reference" data-url="/openapi.json"></div>
    {{-- PB-5 / INFRA-01: Scalar is bundled and version-pinned via
         package.json + Vite; no CDN, no SRI required. --}}
    @vite('resources/js/scalar.js')
</body>
</html>
