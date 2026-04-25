# Contracts Layer — Design Notes

> Status: thinking note, not a scoped plan. Captured for later iteration.
> Companion to `langfuse-integration-plan.md`, `claude-plugin-import-notes.md`,
> and `orchestrator-comparison-notes.md`. The four together sketch URGE's
> transition from prompt registry to governance plane for prompt-centric LLM
> systems, and its positioning relative to established agentic orchestrators.

## The idea

A policy/governance layer in URGE that defines what can execute, how, against
which providers, up to what cost. Sits between the registry (what exists) and
the runtime (modes 1 and 2 execute), enforcing constraints before dispatch and
tracking budgets via Langfuse actuals.

Contracts answer **"who can execute with what resources."** That's orthogonal
to URGE's existing role/team auth, which answers "who can read/edit." Different
axis; both needed.

## Why now — three features that compose

Three directions converged into a coherent picture:

- **Plugins** make cost surface unpredictable. Third-party multi-agent imports have unknown cost profiles until executed. Contracts let import + run be safe.
- **Langfuse** provides the actuals to define informed budgets. Without observability, you guess. With it, you set ceilings against ground truth.
- **Contracts** close the loop: observe → budget → enforce → observe.

Each sharpens the others. Without contracts, plugins + Langfuse gives you
visibility but no guardrails. Without plugins, contracts + Langfuse just
polices URGE's existing surface. Without Langfuse, contracts have no data
source for cost decisions.

## Stay in lane

Contracts govern **URGE's own execution surface** — runs dispatched through
`LlmDispatchService`, pipelines triggered via MCP/API/SPA, plugin imports
running through URGE Pipelines. **Not** arbitrary LLM traffic from unrelated
apps. URGE is not an AI gateway (LiteLLM, Portkey, Helicone's gateway occupy
that space and will outpace URGE on gateway features if URGE chases them).

URGE's differentiator is **registry-aware contracts**: tying policy to
specific prompts, versions, or plugin-Collections. Gateways see HTTP requests;
URGE sees prompt identity. That's a surface gateways can't build.

## Contract dimensions (MVP)

Three, not thirty. Pick the high-value, well-understood ones:

| Dimension | What it controls | Enforcement point |
|---|---|---|
| **Cost ceiling** | max per run + max per team-per-day | pre-run estimate (tokens × price); post-run actuals from Langfuse |
| **Provider allowlist** | which `LlmProvider` drivers permitted | pre-run, before dispatch |
| **Model allowlist** | which model strings permitted | pre-run, before dispatch |

Defer (post-MVP): rate-window contracts (existing rate limit covers some of
this), time-of-day contracts, tool allowlists for agents, conditional contracts
(if-X-then-Y), approval workflows for over-ceiling runs.

## Scope of contract application

Polymorphic, similar to `CollectionItem`:

- `user` — apply to all runs by a specific user
- `team` — apply to all runs in a team context
- `api_key` — apply to all runs authenticated by a key (extends existing per-key prompt scoping)
- `prompt` / `prompt_version` — apply when this prompt is being run
- `pipeline` — apply when this pipeline runs
- `collection` — apply when running anything inside this Collection (matters for plugin-imports)

Multiple contracts can apply simultaneously; resolution order matters. **v1
rule: most-restrictive wins** (intersection of allowlists, minimum of
ceilings). Simple, predictable, no priority/precedence machinery needed.

## Enforcement modes

- `block` — deny if violated (default for cost ceilings + allowlists)
- `warn` — log violation, allow run, surface in admin UI
- `require_approval` — defer to v2; needs pending-approval-queue UI

## Data model sketch

- `Contract` — scope_type + scope_id (polymorphic), dimensions (JSON), mode, created_by, timestamps. Soft-deletable; versioned (audit trail of what was active when).
- `ContractViolation` — contract_id, subject (polymorphic to user/run/etc.), attempted_action (JSON), enforcement_taken, timestamp. Audit log; needs retention policy.

## Enforcement architecture

- **`ContractEvaluationService`** — pure function: `(subject, attempted action, current spend) → (allowed | blocked | warn, reasons, applicable_contracts)`.
- **Pre-run check** — wedge in `LlmDispatchService::dispatch()` before HTTP call. Estimate cost, evaluate, deny or warn. Same wedge in MCP `run_pipeline` handler. Same wedge in Pipeline channel iteration boundary.
- **Post-run cost recording** — extend the Langfuse score-sync command (`urge:sync-langfuse-scores`) to also pull cost actuals; update per-team / per-user / per-key running totals. Cron-driven, eventual consistency.
- **Daily/monthly windows** — cost windows reset on schedule via existing scheduler.

## Where contracts can and can't enforce (mirrors plugin execution modes)

- **Mode 1 (URGE-native)** — full pre + post enforcement. ✓
- **Mode 2 (chat UI via MCP `run_pipeline`)** — full enforcement at the MCP tool boundary. ✓
- **Mode 2 pure form (chat UI as registry only)** — URGE serves prompt content, doesn't dispatch. Cannot enforce. Recommended contracts surface as advisory metadata in MCP `get_prompt` responses; client can choose to honor.
- **Mode 3 (native Claude Code)** — URGE doesn't run, can't enforce. URGE can publish a recommended contract per plugin and surface after-the-fact reports if Claude Code is instrumented (mode-3 instrumentation: out of scope).

First-class for paths URGE executes; advisory for paths it doesn't. Same
pattern as the observability story.

## How the three features compose

URGE is no longer "a prompt registry." With the three pieces in place it is
the **governance plane** for prompt-centric LLM systems:

- **Registry** — what prompts exist, versioned, composable, branchable (existing URGE)
- **Runtime** — modes 1 and 2 execute prompts/pipelines (existing URGE)
- **Observability** — Langfuse pairs to capture per-run signal and aggregate stats (`langfuse-integration-plan.md`)
- **Content imports** — Claude plugins become first-class versioned, runnable, observable artifacts (`claude-plugin-import-notes.md`)
- **Policy** — contracts gate execution by cost, provider, model, scope (this file)

Cross-reinforcement:

- Langfuse gives contracts their cost-actuals data source
- Contracts give plugin imports the safety to be enabled (especially for third-party plugins)
- Plugins give Langfuse a structured, registry-aware observability target nobody else has
- Contracts give Langfuse cost data semantic meaning (this $X was a violation; that $Y was within budget)
- Registry gives contracts their identity surface (gateways can't bind policy to "v3 of slug X")

This is a real product, not a collection of features. The three notes together
sketch the next phase of URGE's positioning.

## Honest caveats

- **Estimation accuracy.** Pre-run cost estimates depend on token-count guessing (input known, output uncertain). Acceptable: ceilings should err generous; post-run actuals from Langfuse correct over time.
- **Contract precedence.** "Most restrictive wins" is fine for v1 but inevitably someone wants override hierarchy ("admin's contract beats team's"). Don't build it preemptively; let real conflicts shape the rules.
- **Audit pressure.** `ContractViolation` table will grow; needs retention policy.
- **Cold start.** Day one of contracts has no Langfuse data yet. Daily/monthly budgets fall back to estimates until actuals catch up.
- **MCP-driven enforcement UX.** A blocked run via MCP returns an error to the chat client. The user reads it in chat and adjusts — reasonable UX. But many concurrent users hitting team budget caps could surface as confusing-from-the-LLM-side errors. Documented limitation, not a blocker.

## Open design questions (for later)

- Do contracts apply during pipeline channel iteration (per-channel) or only at pipeline boundary (per-run)? Per-channel is more granular, more expensive to evaluate. Probably per-run for v1.
- How are pre-run cost estimates calibrated? Static price tables per provider/model + tokenizer per provider, or learned-from-Langfuse moving average?
- Should contracts be versioned like prompts? An audit trail of "this contract was active when violation X happened" is needed. Probably yes — soft-delete + version on edit.
- Does importing a plugin auto-create a recommended contract for it? Probably yes — derived from declared `model` + estimated channel count + safety multiplier.
- What's the relationship between contracts and OAuth scopes (`mcp:read`, `mcp:write`, `mcp:admin`)? Scopes gate API surface; contracts gate execution within that surface. Compose, don't replace.

## Future direction (parked, not in scope)

The pattern (registry + runtime + observability + content imports + policy)
generalizes to other agentic orchestrators — CrewAI, LangGraph, AutoGen,
OpenAI Swarm, OpenCode/OpenHands, etc. Defer until integration #2 has a
concrete user asking for it; abstractions extracted from two real cases beat
abstractions invented from one.
