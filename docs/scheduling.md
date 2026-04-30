# Scheduling Periodic Prompt Runs

URGE itself doesn't run prompts on a schedule — yet. Until the internal scheduler ships, you drive cadence from outside URGE: a cron job, n8n flow, scheduled Claude Code task, or any orchestrator you already use. URGE just receives the result and archives it as a normal `Result`.

This document shows how to wire that up so the resulting time series is well-tagged and easy to filter later.

## The `run_source` tag

Every `Result` carries an optional `run_source` field with two valid values:

- `manual` — ad-hoc, one-off run. Default treatment when omitted.
- `scheduled` — produced by a periodic / scheduled run.

It's separate from the existing `source` enum (`api | manual | import | mcp`), which describes the *protocol* a result arrived via. `run_source` describes the *cadence*. A scheduled cron job will typically post via `source = api` (or `mcp`) and tag `run_source = scheduled`.

Tagging matters because:

- The Workspace results panel can filter to "Scheduled only" — see your time series at a glance.
- The upcoming analytical pipeline (Phase 2) will use this filter to ingest only the periodic runs as input for trend / drift analysis.
- The internal scheduler (Phase 3) will set this tag automatically; using it now means your external setup is forward-compatible.

## Recipe 1 — cron + curl

A daily run that asks an LLM to summarize something, then archives the result back to URGE:

```bash
# /etc/cron.d/urge-daily-summary
0 9 * * * urgeuser /usr/local/bin/urge-daily-summary.sh
```

```bash
#!/usr/bin/env bash
# urge-daily-summary.sh
set -euo pipefail

URGE_BASE="https://urge.example.com"
URGE_KEY="urge_..."
PROMPT_OWNER="me"
PROMPT_SLUG="daily-summary"

# 1. Render the prompt URGE-side (with today's variables filled).
RENDERED=$(curl -fsS -X POST \
    "$URGE_BASE/api/v1/prompts/$PROMPT_OWNER/$PROMPT_SLUG/render" \
    -H "Authorization: Bearer $URGE_KEY" \
    -H "Content-Type: application/json" \
    -d '{"variables":{"date":"'"$(date -I)"'"}}' \
  | jq -r '.data.rendered')

# 2. Run it against your LLM of choice (any provider — here, OpenAI).
RESPONSE=$(curl -fsS -X POST https://api.openai.com/v1/chat/completions \
    -H "Authorization: Bearer $OPENAI_KEY" \
    -H "Content-Type: application/json" \
    -d "$(jq -n --arg p "$RENDERED" '{
          model:"gpt-4o-mini",
          messages:[{role:"user",content:$p}]
        }')" \
  | jq -r '.choices[0].message.content')

# 3. Archive it back to URGE, tagged scheduled.
curl -fsS -X POST \
    "$URGE_BASE/api/v1/prompts/$PROMPT_OWNER/$PROMPT_SLUG/results" \
    -H "Authorization: Bearer $URGE_KEY" \
    -H "Content-Type: application/json" \
    -d "$(jq -n --arg r "$RESPONSE" '{
          version:1,
          response_text:$r,
          provider_name:"OpenAI",
          model_name:"gpt-4o-mini",
          run_source:"scheduled"
        }')"
```

Steps 1 and 2 can be collapsed into a single call to `POST /api/v1/prompts/{u}/{slug}/run-pipeline` if URGE is configured with the LLM provider — see Recipe 3.

## Recipe 2 — Claude Code scheduled task (MCP)

If you already have URGE wired into Claude Code as an MCP server, a scheduled task can just call the existing tools:

1. Use `render_prompt` with `slug` and any variables to get the rendered text.
2. Run the response yourself in Claude Code.
3. Call `store_result` with `slug`, `response_text`, and `run_source: "scheduled"`.

The `run_source` argument is a documented enum on the `store_result` MCP tool — Claude Code sees it in the schema and will pick it up automatically.

## Recipe 3 — when URGE has the LLM provider configured

If the prompt has an LLM provider set up in URGE's Settings → LLM Providers (so URGE can dispatch the call itself), you skip the "render then call provider then store" dance and just call `run-pipeline`:

```bash
curl -fsS -X POST \
    "$URGE_BASE/api/v1/prompts/$PROMPT_OWNER/$PROMPT_SLUG/run-pipeline" \
    -H "Authorization: Bearer $URGE_KEY" \
    -H "Content-Type: application/json" \
    -d '{
      "template_slug": "summary",
      "variables": {"date": "'"$(date -I)"'"},
      "run_source": "scheduled"
    }'
```

URGE dispatches every channel server-side, archives the results with `run_source = scheduled`, and returns the result IDs. One HTTP call per scheduled run.

## Verifying

In the Workspace results panel, tick the **Scheduled** filter to see only periodic runs. Results tagged `scheduled` show a small `scheduled` pill on the result card.

Via the API:

```bash
curl -fsS "$URGE_BASE/api/v1/prompts/$PROMPT_OWNER/$PROMPT_SLUG/results?run_source=scheduled" \
    -H "Authorization: Bearer $URGE_KEY" | jq
```

Via MCP: `get_results` accepts a `run_source: "scheduled"` filter argument.

## What's coming next

- **Phase 2** — pipeline channels that take a *result history* as their input. Combine that with this scheduling setup and you get automatic trend/drift analysis on top of your time series.
- **Phase 3** — internal scheduler. Define cadences inside URGE, point at any configured `LlmProvider`, and let the server run them on its own clock. Same `run_source = scheduled` tag, no external cron needed.

Both are forward-compatible with anything you set up using the recipes above.
