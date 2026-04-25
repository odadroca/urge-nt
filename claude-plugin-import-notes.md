# Claude Plugin Import — Design Notes

> Status: thinking note, not a scoped plan. Captured for later iteration.
> Companion to `langfuse-integration-plan.md`, `contracts-layer-notes.md`,
> `orchestrator-comparison-notes.md`, and `narrated-observability-notes.md`.
> The five together sketch URGE's transition from prompt registry to
> governance plane for prompt-centric LLM systems (registry + runtime +
> observability + content imports + policy), its positioning relative to
> established agentic orchestrators, and the narrated-vs-measured
> observability framing for mode-2-pure-form runs.

## The idea

Let URGE import Claude plugins (e.g. the "superpowers" plugin pack and similar)
by reading the plugin's manifest + skill/command/agent files. The imported
content lives in URGE as Prompts + a wrapping Collection, gaining version
control, branching, team sharing, fragment composability, and a runnable
test/archive surface — all things plugin repos don't natively offer.

The deeper unlock isn't the import itself. It's that once plugin content lives
in URGE, **cross-plugin distillation** becomes possible: take 10 "code review"
skills from different plugins, synthesize a best-of into a unified skill. That
workflow doesn't exist anywhere today.

## Why it works structurally

A Claude plugin isn't monolithic — it's a bundle of content primitives that
URGE already has shapes for:

| Plugin piece | URGE mapping | Fit |
|---|---|---|
| Skill (`.md` + YAML frontmatter) | `Prompt` (body = content, frontmatter = metadata/tags) | Native — storage is identical |
| Slash command | `Prompt` | Native — a command is a prompt that gets injected |
| Agent (`.md` + tools + model) | `Prompt` + extra metadata (allowed tools, model) | Fits with one new JSON metadata column, or via tags |
| Multi-agent orchestration (orchestrator + sub-agents) | `Pipeline` (channels = sub-agents, synthesis channel = orchestrator) | Native — a Pipeline *is* a runtime orchestration graph |
| Plugin itself (the bundle) | `Collection` containing Prompts **and** Pipelines | Perfect — curated polymorphic grouping is what Collections are |
| Hooks (shell commands) | — | Out of scope; not prompts |
| MCP servers (connection config) | — | Out of scope; not prompts |

Two layers, not one. **Static content** (skills, commands, non-orchestrating
agents) maps to Prompts. **Runtime orchestration** (an orchestrator agent
fanning out to sub-agents and synthesizing) maps to Pipelines. The plugin
wrapper is a Collection that can hold both.

Note: `CollectionItem`'s polymorphic types are currently `prompt_version |
result | collection`. To represent full plugin structure, extend to include
`pipeline`. Small principled extension; matches how Collections already work.

## Why it's valuable (beyond "I can import things")

Import is a means; the real wins only appear once plugin content lives in URGE:

1. **Version control with branches.** Plugin skills evolve informally today, typically via commits on a git repo. URGE's branch/pin model is better suited to *experimental* skill iteration (try variant, measure, promote).
2. **Fragment composability.** URGE's `{{>slug}}` lets multiple skills share a common "output format" or "tone" fragment. Plugins don't natively have this — authors copy-paste.
3. **Run + archive.** URGE can execute a skill against a provider and store Results. Plugin skills are tested today by "run Claude Code and hope." URGE gives a proper testbed with history.
4. **Teams.** Plugins are usually solo-authored or PR'd. URGE's team model lets a group iterate privately before publishing.
5. **Cross-plugin distillation.** The new thing. Synthesize across imported plugins. Genuinely novel value.

## Execution modes (URGE is not registry-only)

URGE already supports multiple execution pathways, and the same imported
plugin-Pipeline can be run via any of them from the same stored artifact:

1. **URGE-native execution** — `LlmDispatchService` + configured `LlmProvider` drivers (OpenAI/Anthropic/Mistral/Gemini/Ollama/OpenRouter) run the Pipeline directly. Paywalled against the user's API keys. Best for deterministic, scriptable, archived runs.
2. **Chat-client-driven via MCP** — Claude Desktop, Mistral Le Chat, or a Custom GPT reasons in the chat UI; URGE serves as the ops layer (render prompts, run pipelines, archive results) via MCP or Custom-GPT-Actions. **The reasoning tokens are on the user's chat subscription, not metered API calls.** This is the economic sweet spot: the paywalled chat surface becomes the reasoning driver, URGE provides the structured runtime the chat UI doesn't have. Flow example: ask Claude to draft a prompt → save to URGE → ask Claude to render + run + store the result, all from the chat UI.
3. **Claude-Code-driven** — Claude Code runs plugins locally (skills/hooks execute in the IDE/CLI). URGE as pure registry. Least interesting for URGE because URGE does no work.

The plugin-import value proposition sharpens once modes 1 and 2 are in view:
you're not just versioning plugin content, you're unlocking three execution
pathways over it. Mode 2 specifically — chat-UI-as-reasoning-driver, URGE-as-
ops-layer — sidesteps API quotas entirely for interactive use.

## Observability angle (URGE + Langfuse, paired)

When URGE executes a plugin-Pipeline (modes 1 and 2-via-pipeline-run) AND the
Langfuse integration is enabled, you get an observability view over Claude-
plugin orchestration that doesn't exist anywhere today.

**What becomes visible:**

- **Per-agent generation** — every sub-agent invocation as a first-class generation with input/output, tokens, latency, cost, model, prompt version
- **Preserved hierarchy** — synthesis-of-parallels relationship visible in the trace tree (orchestrator parent, sub-agent children — not flat siblings)
- **Cross-run aggregation** — slowest / most expensive / most failure-prone sub-agent across N runs
- **Version-pinned analysis** — did v3 of a sub-agent improve or regress vs v2 on comparable inputs
- **Per-role cost attribution** — plugin-wide spend broken down by which sub-agent burned what
- **Evaluation trend** — URGE's `EvaluationService` scores + Langfuse human annotations pulled back → per-agent score over time
- **Cross-plugin signal** — "across all imported 'code review' agents from all imported plugins, which score highest" — only answerable because everything is normalized into one registry

**What remains invisible (honest boundary):**

- **Chat-UI-as-registry pure form (mode 2).** When a chat client fetches prompt content from URGE and reasons natively, that LLM call doesn't touch URGE and doesn't emit traces. URGE sees only the prompt fetch and (if the client calls back) the result storage.
- **Plugin running natively in Claude Code (mode 3).** Invisible entirely; Claude Code doesn't emit to Langfuse. To gain observability you must route execution through URGE.

Strategic consequence: **observability is itself a reason to run plugins
through URGE**, not just to store them. Plugin authors today have zero
observability of their plugins in the wild. Even Anthropic — who sees the API
calls — doesn't see the *structure of plugin orchestration* around those calls,
because that structure lives in plugin code on each user's machine. URGE +
Langfuse for a routed-through-URGE plugin provides a vantage point nobody else
currently has.

## Orchestrator framing

Parallel to the Langfuse story: URGE and Claude Code are **peers**, not parent-
and-add-on. URGE owns the content primitives (prompts, fragments, pipelines)
and provides execution via modes 1 and 2. Claude Code owns local IDE/CLI
execution (mode 3). Each tool stays in its strength; the imported plugin lives
simultaneously in both worlds.

## Two value propositions stacked on the same import

Plugin import is really two distinct value propositions on shared
infrastructure:

1. **URGE as plugin authoring layer.** Branching, fragment composability, team sharing, run + archive loop, cross-plugin distillation. Sells to plugin *creators* iterating on their own work.
2. **URGE as plugin observability layer.** Import third-party plugin, run through URGE, see what happens under the hood via Langfuse. Sells to plugin *users* (individuals or teams) who want insight, not authorship.

They don't conflict, but they prioritize the MVP differently:

- **Authoring-shaped MVP** emphasizes branching, fragments, teams, editing UI. Assumes the user is a creator.
- **Observability-shaped MVP** emphasizes source tracking, re-import, running imported plugins faithfully as-is, trace emission, and cross-plugin aggregation. Assumes the user is a consumer.

The observability audience is larger and doesn't require the user to be a
plugin author at all. Worth noticing which audience the MVP targets first —
it's a real scope fork and the right choice depends on who you're building for
(plus which audience URGE already attracts today).

## MVP scope (when this gets picked up)

- **Import-only.** Parser for `plugin.json` + `skills/`, `commands/`, `agents/` dirs → Prompts (static content) + Pipelines (orchestration-shaped content) + a wrapping Collection. Defer round-trip export.
- **Detect orchestration shape.** For plugins with a clear orchestrator + sub-agents pattern that matches URGE's Pipeline model (static parallel channels + single synthesis pass), import as a Pipeline. For plugins with richer dynamic orchestration (conditional dispatch, loops, recursive spawning), import the agents as Prompts and flag loudly that manual Pipeline composition is needed. **Silent approximation is worse than nothing** — fidelity matters more than import coverage.
- **Extend `CollectionItem` polymorphism** to include `pipeline` alongside existing `prompt_version | result | collection`. Required for the Collection-of-Prompts-and-Pipelines shape.
- **Source tracking column** — `Prompt.source_url` nullable (repo URL + commit hash + file path). Cheap; enables diffing/re-import later without committing to full sync. Apply the same pattern to imported Pipelines.
- **Tags over new type enum.** Use `tags: [skill, agent, command]` on Prompts rather than expanding `Prompt.type` beyond `prompt|fragment`. Less schema churn. Promote to a dedicated type only if behavior genuinely diverges.
- **CLI first, MCP tool second.** `php artisan urge:import-plugin <path-or-url>` mirrors the existing `urge:import-v1` command. Add an `import_plugin` MCP tool once the human workflow is validated — note that mode-2 execution (chat-UI-driven) makes the MCP tool the more valuable surface once it lands.
- **Loose schema parser.** Treat unknown YAML frontmatter keys as
  metadata-preserved, not metadata-rejected. Plugin conventions are still
  shifting; don't lock in early.

## Deliberately out of scope (MVP)

- **Round-trip export** (URGE → plugin format). Where the "author in URGE, publish as plugin" story becomes real, but where complexity bites (decoding `{{>slug}}` back into plugin-native syntax, or inlining if no equivalent). Wait until import + iteration prove useful alone.
- **Hooks and MCP-server representation.** Not prompts. URGE shouldn't model them.
- **Plugin marketplace browsing.** Discovery is a separate problem; out of scope for content storage.
- **Live sync** with the source repo. Source tracking column enables manual re-import, which is enough.

## Honest caveats

- Plugin taxonomy is still shifting. "Superpowers" is one convention (Jesse Vincent's pack); others exist and conventions will keep moving. Loose schema parsing matters.
- Feature value depends on whether plugin authors want URGE as their authoring layer. Unknown. Start with *your own* plugin workflow as the validation case — if URGE makes *you* iterate plugins faster, that's a strong signal; if not, don't ship beyond MVP.
- **Orchestration fidelity gap.** URGE's Pipeline is one specific shape: static parallel channels + single synthesis pass. Claude Code's agent dispatching is dynamic (orchestrator decides mid-run whether to spawn sub-agents, can loop, can recurse). Many interesting plugins live in that richer space. For those, URGE's Pipeline is a lossy approximation. The importer must detect mismatch and refuse rather than silently flatten.
- The mapping I described is structural. The semantics may surprise — e.g., a slash command's "argument hint" frontmatter key has no obvious URGE analog. Discover these case-by-case during implementation, don't pre-design for them.

## Open design questions (for later)

- Does an imported plugin become *one* Collection per plugin, or one Collection per plugin-version? (Probably per-plugin, with PromptVersion handling content evolution.)
- How are agent tool-allowlists stored? New `Prompt.metadata` JSON column, or piggyback on `variable_metadata`?
- Does importing the same plugin twice update existing Prompts or create new ones? (Probably: by `source_url` match → new PromptVersion on existing Prompt; otherwise create new.)
- Should the import respect plugin git history and create one PromptVersion per source commit? (Probably no for MVP — too clever. Single version representing "as-of-import" is fine.)
