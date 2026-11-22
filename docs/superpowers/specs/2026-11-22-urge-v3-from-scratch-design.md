# URGE v3 — From-Scratch Design

Date: 2026-11-22
Status: DRAFT for owner approval. Nothing is built until this document is approved.
Supersedes: nothing. urge-nt (v2) and calane-claude remain as they are; this describes a new repository.

---

## 1. Intent

URGE v3 is a **research-record system for LLM work**, self-hosted by a household
or a very small group, used by the LLMs its people talk to. Each person's work is
private unless they choose to share it with the others on the instance.

The artifact it protects is a *research tree with a verdict*: one root prompt
holding the shared parameters, branches per variant, every branch run on one or
more models, every answer archived against the exact version that produced it,
and a judge or distiller that reads the whole archive and arbitrates. Months
later the full record, including the verdict and who contributed what, is still
retrievable and legible, by a human or by an LLM, without the conversation that
produced it. When it is shared, another person on the instance can re-enter the
same record, build on it, and run their own branches against it.

Everything else in this document exists to make that artifact reliable to
create, reliable to re-enter, safe to share within the instance, and cheap to
host.

### 1.1 Why now

The owner re-read a tiny-house research tree in URGE v2 months after producing
it and found the record intact and useful, but had forgotten the system could do
this. The code that produced the record is roughly 10–15% of URGE v2; the rest
was built for a hypothetical multi-tenant product (namespaces, teams, roles, an
OAuth authorization server) and then had to be security hardened. The real
sharing need is one other household member. Calane, a separate kernel the owner built afterwards, solved the parts
URGE v2 does badly (reliable fan-out, linked run records) and by contract refuses
to do the parts URGE does well (prompt memory). v3 puts the two halves in one
process.

### 1.2 What happened in practice (ground truth from the live v2 instance)

- The escritório tree: one prompt, four version branches (`main`,
  `sem-isolamento`, `sem-banho`, `sem-isolamento-sem-banho`), each forked from a
  successive `main` version. Each branch run on Claude Opus (via MCP) and ChatGPT.
- Non-Claude clients could not write back over MCP. ChatGPT and Mistral results
  were pasted by hand with notes like "manually added because of GPT issues
  communicating with the MCP server". Claude ran once as a proxy for Mistral.
- Which branch a result belongs to is recoverable only via version number and a
  free-text note. The judge step relied on the owner aiming it by hand.
- Two pipelines were meaningful: `evaluation` (five parallel single-dimension
  scorers returning JSON) and `best-of` (one client-executed synthesis channel
  labelled `distiller`).
- Scheduled work (weekly AI recap) runs as a Claude Routine that renders the
  prompt from URGE, executes natively with web search, and stores back tagged
  `scheduled`.

---

## 2. Decisions recorded from the owner interview (2026-11-22)

Owner decisions, verbatim intent:

| # | Topic | Decision |
|---|---|---|
| 1 | Audience | A **shared instance for a small allowlist of people** (the owner and family), **private by default with one visibility flag**. No teams. Others may self-host their own copy. (Revised 2026-11-22 from "single operator"; see §7.6 and Appendix D.) |
| 2 | Hosting | Small container or VPS. Language unconstrained. PHP not required. |
| 3 | Human UI | No full editor. Authoring happens through LLM clients over MCP. |
| 4 | Data | Clean start. v2 instance stays up as an archive. No importer. |
| 5 | Launch clients | Claude Code, Claude.ai, ChatGPT/Codex. Mistral and others later. |
| 6 | Output contracts | Optional JSON Schema per channel. |
| 7 | Cadence | Passive at launch. External schedulers trigger; URGE tags and stores. |
| 8 | Web access | Declared per channel. Server dispatches to providers whose search tool is configured; otherwise hands the channel to the client. |
| 9 | Build approach | B: one new TypeScript repo absorbing Calane's kernel and rebuilding URGE's memory layer. |
| 10 | Store | Owner delegated; designer's call is SQLite (see §7.7). |

Designer's calls the owner did not contradict (open to override):

- Read-only web viewer exists, but after the machine surface, not before.
- Launch providers: OpenRouter, Anthropic, one OpenAI-compatible driver.
- No outbound telemetry by default. Provider keys stay on the box.
- "Door kept open" means: visibility is modelled as an **audience reference**,
  not a boolean, and the only audience built in v3 is `instance` (everyone on
  the allowlist). A Team later is one more audience kind plus a membership table,
  with no change to prompt, result, or run columns. No team CRUD, roles, or
  namespaces are built.
- The manual paste-with-provenance path survives as one API/MCP call
  (`store_result`), with no form built for it.

---

## 3. Goals, non-goals, anti-goals

### Goals

1. An LLM client, given one instruction, can create a prompt, version it, fork
   branches, run the tree across configured models, and store a judged verdict,
   with every result linked to its run, branch, version, and provenance.
2. Reliable multi-model fan-out executed by the server when a provider is
   configured, with real concurrency, retries, and optional schema validation.
3. Client execution as a first-class mode: when the calling LLM is the best
   executor (it has web search, or no provider key exists), URGE hands back the
   rendered channel and accepts the completion **into the same run**.
4. A run record that is self-describing and exportable as Markdown plus JSON,
   with content hashes of every prompt and schema that shaped it.
5. One remote MCP server that Claude.ai, ChatGPT, and Codex each accept, with
   auth that is a thin resource-server layer and nothing more.
6. One-process deployment on a small container with a single SQLite file.

### Non-goals (for v3; may be revisited by the owner only)

- Multi-tenancy. Teams, team CRUD, roles, per-user namespaces in slugs or URLs.
  (Sharing between allowlisted people on one instance *is* in scope: §7.6.)
- An authorization server. URGE validates tokens; it never issues them to third parties.
- A prompt editor UI, visual composer, canvas, drag-and-drop pipeline builder.
- An internal scheduler. URGE never initiates a run on its own clock.
- Federation, signing, A2A agent cards, model-selection harness (Calane features
  that stay in Calane unless a concrete run needs them).
- Importing v2 data.
- Collections, nested collections, share links.
- Billing, marketplace, hosted SaaS.

### Anti-goals (would make v3 a failure even if it works)

- A tool surface that grows past what OpenAI-style callers accept (~30) or that
  makes an LLM guess which of several near-identical tools to call.
- Results that exist without a link to the run, branch, and version that produced them.
- An MCP layer and a REST layer that disagree (the v2 failure: separate lookup,
  separate scope enforcement, separate rate limits).
- A deployment that needs a second always-on process to be correct.
- Hardening work that exists only because surface area was added speculatively.

---

## 4. Constraints

- **Small allowlist, private by default.** Every record has one owner. A record
  is visible to its owner, or to everyone on the instance allowlist when its
  prompt or pipeline is marked `shared_with: [instance]`. There are no other
  audiences in v3. Every read goes through the one visibility filter in
  `authorize()`; no query may bypass it.
- **TypeScript / Node 22+.** Chosen because the reusable executor is TypeScript.
- **TypeBox is the single schema source** for internal contracts, REST
  validation, MCP tool descriptors, OpenAPI, and user-authored output schemas
  (which are JSON Schema files or inline JSON Schema). No second schema library.
  (Carried from Calane; it worked.)
- **Schema-validated outputs are optional per channel**, never mandatory.
- **Prose is first-class.** The research record is mostly prose. Nothing may force
  prose into `{ "text": "..." }`.
- **No outbound calls except to configured LLM providers, the configured
  identity provider, and an OpenTelemetry collector the operator explicitly
  configures.** Telemetry export is off by default.
- **Web access is a capability a channel declares and a provider either has or
  lacks.** Routing is decided per channel at run time; it is never silent.
- **The v2 instance and the Calane repo are not modified by this work.**

---

## 5. Approaches considered

**A. URGE as a thin layer over Calane, two repos.** Calane unchanged; new URGE
holds prompt memory and MCP, plugs into Calane's registry interface. Rejected:
Calane channels reference prompts by file path with no variables or includes, so
either Calane's frozen contract breaks or URGE pre-renders everything and the run
record loses the template lineage. Two codebases, two MCP servers to keep
consistent; the escritório linkage already broke across a process boundary.

**B. One new repo absorbing Calane's kernel and URGE's memory.** Chosen.
Carry roughly 2.5k lines of Calane (specs, channel/pipeline executor with
concurrency and retries, Ajv validation, hashing, bundle export, SQLite store).
Rebuild from URGE only the concepts that proved themselves. One MCP server,
coarse tools. Cost: imported code brings its shape; each piece must be
re-justified, not copied.

**Calane's fate (owner decision, 2026-11-22):** v3 is the single tool. Calane is
frozen as an archive and reference; it is not developed in parallel and nothing
merges back into it. The separation that survives is a **package boundary, not
a runtime switch**: the execution kernel (specs, executor, validator, hashing,
bundle, telemetry sink) is its own package inside the v3 monorepo with no
dependency on the prompt store, so it stays inspectable and could be published
standalone later. There is no "kernel-only" mode of the running server.

**C. Concept-only rewrite, no code reuse.** Rejected: slowest, discards a tested
executor, and a third hand-written version of the same executor would re-derive
the same shape.

---

## 6. The system in one picture

```
Claude Code (stdio) ─┐
Claude.ai (HTTP) ────┤                           ┌─ Memory ──────────────────────┐
ChatGPT (HTTP) ──────┼─► MCP server ─┐           │ Prompt · Version · Branch      │
Codex (HTTP) ────────┘               │           │ Fragment (= prompt, type)      │
                                     ├─► Application services ─┤ Pipeline · Channel             │
cron / Routine / CLI ─► REST (mirror)┘   (one layer, one       │ Run · ChannelResult · Result   │
                                          authorization check)  │ Provenance                     │
                                                 │              └────────────────────────────────┘
                                                 ▼
                                   ┌─ Execution kernel (from Calane) ─┐
                                   │ render → route (server|client)   │
                                   │ → provider call (concurrent)     │
                                   │ → parse → validate (optional)    │
                                   │ → repair → store → synthesis     │
                                   └──────────────────────────────────┘
                                                 │
                                        SQLite (WAL) + bundle export
```

One rule above all: **MCP, REST, and CLI are thin adapters over the same
application services.** No adapter looks up a prompt, resolves a version, checks
authorization, or builds a run on its own.

---

## 7. Design

### 7.1 Domain model

All entities carry `id`, `created_at`, `owner_id` (a **Person** on the instance
allowlist).

**Person** — `id`, `display_name`, `identity_claims[]` (IdP `sub` or email values
that map to this person), `is_admin` (may edit the allowlist and providers),
`created_at`. Configured by the admin via CLI or REST; there is no sign-up.

**Visibility** (on Prompt and Pipeline) — `private` (owner only) or
`shared_with: [audience...]`. The only audience kind in v3 is `instance`. Results
and ChannelResults inherit visibility from their prompt's version; Runs are
visible to their requester and to anyone who can see **every** target and the
pipeline. Providers are instance-wide and admin-managed; their keys are never
returned. Writes (new version, new branch, new run) on a shared prompt are
allowed to anyone who can see it; delete, archive, and visibility changes are
owner-only. Every write records who did it.

**Prompt** — `slug` (unique per instance; no owner prefix in refs), `name`,
`type` (`prompt` | `fragment`), `description`, `tags[]`, `visibility`,
`archived_at?`. A fragment is a prompt whose purpose is to be included. An
include of a prompt the reader cannot see fails the render with a clear error;
sharing a composite does not implicitly share its fragments.

**Branch** — `prompt_id`, `name` (slug-safe, unique per prompt), `head_version_id`,
`forked_from_version_id?`, `is_default`. Exactly one default per prompt.

**Version** — immutable. `prompt_id`, `branch_id`, `number` (global per prompt),
`branch_number` (per branch), `content`, `commit_message?`, `variables[]`
(extracted), `includes[]` (extracted refs), `content_hash` (sha256 of content),
`variable_defaults{}?`. Display: `escritorio@sem-banho#2 (v7)`.

**Prompt ref** — a string the whole surface uses: `slug`, `slug@branch`,
`slug#7`, `slug@branch#2`. Resolution: explicit version → branch head → default
branch head. Every API that takes a prompt takes a ref.

**Target ref** — what a Run may target: a prompt ref (render it as `{{input}}`),
`result:<id>` (the text of one archived Result as `{{input}}`, for scoring or
distilling a single answer), or `run:<id>` (every archive Result of a past run,
one target each). The `evaluation` and `best-of` pipelines target Results.

**Provider** — `slug`, `driver` (`openrouter` | `anthropic` | `openai_compatible`),
`model`, `endpoint?`, `api_key` (encrypted at rest), `capabilities{ web_search:
bool, json_schema: bool }`, `active`.

**Pipeline** — `slug`, `name`, `description`, `channels[]`, `synthesis?`
(a channel), `defaults{ retry, repair, concurrency }`.

**Channel** — `id` (stable within pipeline), `role_label`, `system_prompt`
(may use `{{>slug}}` includes and run-context variables), `execution`
(`server` | `client` | `auto`), `provider_ref?`, `needs_web: bool`,
`output_schema?` (inline JSON Schema or a stored schema ref), `depends_on[]`,
`retry?`, `repair?`, `input_source` (`prompt` | `result_history`),
`input_filters?` (since, limit, run_source, prompt refs).

**Run** — `pipeline_id`, `pipeline_hash`, `targets[]` (resolved prompt refs with
version ids and content hashes), `variables{}`, `status`
(`running` | `awaiting_client` | `completed` | `partial` | `failed`),
`run_source` (`manual` | `scheduled`), `requested_by` (Person id) and
`requested_via` (client name from MCP `initialize`, API key name, or `cli`),
`started_at`, `finished_at?`, `synthesis_result_id?`.

**ChannelResult** — one per (run, channel, target). `status`
(`ok` | `invalid_json` | `schema_error` | `refused` | `timeout` | `error` |
`pending_client` | `skipped`), `raw_output` (always kept), `parsed_output?`,
`validation_errors?`, `repair_attempted`, `rendered_prompt_hash`,
`system_prompt_hash`, `latency_ms?`, `usage?`, **Provenance** (below).

**Result** — the archive row a human or LLM reads later. Every ChannelResult
with output produces one. A Result may also exist without a run (the paste
path, or a single ad-hoc `store_result`). Fields: `kind`
(`answer` | `score` | `synthesis`), `prompt_version_id?` (null for a synthesis
over several targets; the run carries the targets), `run_id?`,
`channel_result_id?`, `text`, `rating?`, `starred`, `notes?`, `run_source`,
Provenance. A channel declares `archive_as` (`answer` default, `score`,
`synthesis`, or `none`). `get_results` returns `answer` and `synthesis` by
default; `kind` filters. A synthesis Result is listed under every prompt its
run targeted.

**Provenance** (embedded) — who produced the text and who recorded it are kept
apart. `executed_by` (`server`: URGE dispatched it; `client`: the connected LLM
ran it itself; `external`: produced elsewhere and relayed, the paste path),
`provider` and `model` (always the producer; free text or provider slug),
`web_used: bool?`, `tokens_in?`, `tokens_out?`, `recorded_via` (the MCP client
name, API key name, or `cli` that made the call), `recorded_by` (Person id),
`recorded_at`. Example: "record this result for `escritorio@sem-banho`, sourced
via web from ChatGPT 5.4 thinking" → `executed_by: external, provider: openai,
model: chatgpt-5.4-thinking, web_used: true, recorded_via: claude-code`.

**Research tree** is not an entity. It is the view "all branch heads of prompt X
plus the runs that targeted them". The owner's workflow is served by letting a
Run target several refs at once (§7.4).

### 7.2 Templating

Carried from URGE v2, tightened:

- `{{name}}` user variable. Strict by default: a missing variable with no default
  fails the render with a list of missing names. `strict=false` renders
  placeholders for preview.
- `{{>slug}}`, `{{>slug@branch}}`, `{{>slug#n}}` includes, recursive, cycle
  detection, depth cap (default 10), expansion budget (characters) to stop
  include bombs.
- Run-context variables available in channel system prompts (carried from
  Calane): `{{input}}` (the rendered target), `{{channel_results}}`,
  `{{channel_results.<id>.raw|parsed}}`, `{{previous_synthesis}}`, `{{run_id}}`,
  plus v3 additions `{{target.ref}}` and `{{target.branch}}` so a judge can name
  which branch said what.
- Rendering is a pure function of (version content, includes at their resolved
  versions, variables). The run record stores the hash of the rendered text and
  the ids of every version that participated.

**Composition ("object-oriented prompts", carried from v2 by owner request):**

- A prompt of `type: fragment` is a reusable piece. Any prompt may be included;
  the type marks intent and filters listings.
- **Binding.** `{{>slug}}` is late-bound: it follows the fragment's default
  branch head as it evolves. `{{>slug@branch}}` and `{{>slug#n}}` are early-bound.
  History is safe either way because every render records the participating
  version ids and hashes.
- **Variable propagation.** A fragment's `{{var}}` becomes a required variable of
  the composite that includes it. Defaults declared on the fragment's version
  apply unless the composite declares its own. There is no argument passing at
  the include site in v3; a fragment that needs different values in two places
  is two fragments or two branches.
- **Reverse lookup.** Each Version stores its extracted `includes[]`, so
  `get_prompt` returns `used_by` (prompts whose current heads include this one),
  and `save_prompt` on a fragment reports which composites' default renders it
  shifts.
- Channel system prompts compose the same way, so a persona or an output format
  is one versioned fragment shared across pipelines.

### 7.3 Execution kernel (carried from Calane, adapted)

Pipeline execution follows Calane's executor: build a plan from `depends_on`
(topological levels), run each level's channels concurrently under a global cap
and per-provider caps, retry transient errors with exponential backoff, parse
JSON when a schema exists, validate with Ajv, attempt repair re-prompts when
configured, always keep raw output, aggregate status, then run synthesis as a
normal channel over the level's results.

Adaptations:

- **Targets.** A run executes each channel once per target. Channel × target is
  the unit of work. Synthesis sees all of them with `{{target.ref}}` labels.
- **Routing per channel×target** (`execution: auto`):
  1. If the channel's provider is configured and active, and (`needs_web` is
     false or the provider has `web_search`), execute on the server.
  2. Otherwise mark `pending_client` and return the rendered system prompt and
     user prompt for that channel×target to the caller.
  `execution: server` fails loudly instead of falling back. `execution: client`
  never dispatches.
- **Client completion.** `complete_channel(run_id, channel_id, target_ref,
  output, provenance)` records the result into the run. When all parallel
  channel×targets are in, the run proceeds to synthesis: server-side if the
  synthesis channel routes to the server, otherwise the run returns the rendered
  synthesis prompt and awaits one more `complete_channel`. This is the fix for
  the v2 linkage gap.
- **Persisted state machine, not an in-memory function.** Unlike Calane's
  executor, a v3 run lives in the database as per-channel×target rows and must
  survive a process restart and a `complete_channel` that arrives days later.
  Server-side work runs in-process, asynchronously. `run`, `complete_channel`
  and `finalize_run` accept `wait_ms` (default 20 000, cap 55 000): they return
  as soon as server work for that step finishes or the wait expires, with the
  run snapshot; the caller polls `get_run` for the rest. On startup, any
  channel×target left in `running` becomes `interrupted` and is re-dispatched by
  `run(resume=run_id)`; nothing is silently re-run.
- **Stuck client items.** `finalize_run(run_id)` marks every `pending_client`
  item `skipped`, proceeds to synthesis over what exists, and ends the run as
  `partial`. There is no expiry; URGE never changes a run on its own clock.
- **Telemetry.** Calane's `TelemetrySink` interface and its run/channel events
  are carried with the executor (no-op sink by default). The OpenTelemetry
  exporter is a separate optional package, enabled only by configuration, so a
  stuck `pending_client` or a slow judge call can be followed in the operator's
  own collector. `Run` and `ChannelResult` keep `trace_id`. No metrics or trace
  UI inside URGE.
- **Recursion** (Calane's bounded depth) is carried as a pipeline option but is
  not required for any v3 milestone.
- **Synthesis variants and carry-forward strategies** (labels with no code in
  Calane) are not carried.

### 7.4 The owner's workflow, end to end

"Write a research prompt for X, save it as `escritorio`, fork branches for
`sem-isolamento` and `sem-banho`, run all branches against the configured
models, then judge."

1. `save_prompt(slug="escritorio", content, commit_message)` → v1 on `main`.
2. `save_prompt(slug="escritorio", branch="sem-isolamento", fork_from="escritorio#1", content)` → branch created, v2.
3. Same for `sem-banho` → v3.
4. `run(pipeline="research-fanout", targets=["escritorio@main", "escritorio@sem-isolamento", "escritorio@sem-banho"], variables={...})`.
   - The pipeline has one channel `research` with `needs_web: true`,
     `execution: auto`, provider `openrouter` (no web), and a synthesis channel
     `judge` with `execution: auto`, provider `anthropic`.
   - Router: `research` has no web-capable provider → three `pending_client`
     items returned with rendered prompts. `judge` routes to server.
5. The client executes the three research prompts with its own web search and
   calls `complete_channel` three times with provenance (`recorded_via:
   "claude-code"`, `model`, `web_used: true`).
6. The server runs `judge` over the three results, stores the synthesis Result,
   marks the run `completed`.
7. Months later: `get_run(run_id)` or `get_results("escritorio")` returns the
   tree, every branch's answer, who produced it, and the verdict. `export(run_id)`
   writes a Markdown bundle.

If an OpenRouter model with web search is later configured, step 4 routes
`research` to the server with no change to the pipeline.

### 7.5 Machine surface

**One application-service layer.** Services: `PromptService`, `RenderService`,
`PipelineService`, `RunService`, `ResultService`, `ProviderService`,
`ExportService`. Each takes an `Actor` (resolved once by the adapter) and does
its own authorization check through a single `authorize(actor, action,
resource)` function. Adapters never bypass it.

**MCP tools** (14 at launch, hard cap 16; coarse, verbs not nouns):

| Tool | Purpose |
|---|---|
| `list_prompts(type?, tag?, search?, scope?: mine \| shared \| all)` | browse what the actor can see |
| `get_prompt(ref, render?: {variables, strict})` | content, metadata, branches summary; optionally rendered |
| `save_prompt(slug, content, commit_message?, branch?, fork_from?, type?, tags?, visibility?)` | create prompt, new version, or new branch in one call; `visibility` on create or by owner |
| `render_prompt(ref, variables, strict?)` | rendered text + used/missing variables + resolved includes |
| `list_pipelines()` | browse |
| `get_pipeline(slug)` | channels, routing preview for current providers |
| `save_pipeline(slug, spec)` | create or replace (whole spec; TypeBox-validated) |
| `run(pipeline, targets[], variables?, run_source?)` | start a run; returns run id, server results so far, pending client items with rendered prompts |
| `complete_channel(run_id, channel_id, target_ref, output, provenance)` | client hands back a result into the run |
| `get_run(run_id)` | full run record |
| `list_runs(pipeline?, prompt?, since?, status?)` | browse runs |
| `get_results(ref, run_source?, since?, limit?, starred?)` | history for a prompt (any branch/version) |
| `store_result(ref, text, provenance, notes?, rating?, run_source?)` | the paste / ad-hoc path |
| `finalize_run(run_id)` | skip outstanding client items, synthesize over what exists, end as `partial` |

`export(run_id | ref)` is the 15th if the owner wants it as a tool; otherwise
export is REST/CLI only. Provider CRUD and rating/starring are REST/CLI only;
they are configuration, not conversation.

**MCP resources**: `urge://prompts`, `urge://prompts/{ref}`, `urge://runs/{id}`.

**Transports**: stdio (no auth, local, Claude Code) and Streamable HTTP at
`/mcp` (remote). Both call the same JSON-RPC dispatcher; one switch, not two.

**REST** mirrors the services 1:1 (`/prompts`, `/prompts/{ref}`, `/pipelines`,
`/runs`, `/results`, `/providers`, `/export/...`, `/health`), with an OpenAPI 3.1
document generated from the same TypeBox schemas. It exists for cron, CLI, and
a Custom GPT Action if the owner still wants one after the MCP connector works.

**CLI** (`urge`): `serve`, `mcp-stdio`, `prompt get|save|render`, `run`,
`complete`, `results`, `export`, `provider add|list`, `key create`. Thin over
REST or in-process services.

### 7.6 Authentication and authorization

- **URGE is an OAuth 2.1 resource server, never an authorization server.** It
  publishes RFC 9728 Protected Resource Metadata at
  `/.well-known/oauth-protected-resource` pointing at an external identity
  provider, validates bearer JWTs against the IdP's JWKS (or introspection),
  checks audience/resource (RFC 8707), and maps the token's identity claim to a
  Person on the allowlist. Unknown identities are rejected; there is no sign-up.
  Calane's R2 implementation is the starting point.
- **Client registration happens at the IdP, not in URGE.** The three launch
  clients each accept a pre-registered client id, so Dynamic Client Registration
  is not a requirement: Claude.ai accepts a custom client id and secret;
  ChatGPT accepts static credentials; Codex takes `--oauth-client-id`. If the
  chosen IdP supports DCR or Client ID Metadata Documents, clients may use them;
  URGE does not care. (Verified against vendor docs on 2026-11-22; see Appendix B.)
- **API keys** (`urge_` prefix, sha256 at rest, per-key rate limit) belong to a
  Person and act as that person, for cron, CLI, and scripts. No per-prompt
  scoping (it was unenforced on MCP in v2); `read`/`write` only.
- **stdio has no auth.** It runs on a person's machine with `--as <person>`
  (required when the allowlist has more than one entry; it never defaults to
  "the first user", which the v2 audit flagged).
- **Identity, not scopes, on OAuth tokens.** A token is accepted when its
  signature, audience and expiry check out and its identity claim maps to a
  Person. The IdP therefore needs no custom scope or API definition; it must
  issue JWT access tokens with a configurable audience, accept three redirect
  URIs, and let each allowlisted person sign in (a shared hosted IdP tenant, or
  a self-hosted one with two accounts).
- **`read` / `write` exist only on API keys**, chosen at key creation, for cron
  jobs that should never write. One `Actor` type
  (`person(id, admin) | key(person_id, read|write)`) is resolved by every adapter
  and checked by one `authorize(actor, action, resource)` that applies the
  visibility rule in §7.1. Every list query is built from the same filter.
- **Mixed mode is not offered.** `initialize` and `tools/list` require auth like
  everything else, except over stdio.
- IdP choice is the operator's (any OIDC provider that issues JWTs with a
  configurable audience and lets the operator register three redirect URIs).
  The deploy guide lists one hosted option and one self-hosted option with the
  exact three callback URLs to register.

### 7.7 Storage

- **SQLite, WAL mode, one file on the mounted volume.** Reasons: one operator,
  hundreds to low thousands of text-heavy rows, reads dominate; one process,
  one volume; backup is a file copy; privacy premise kept; Calane already has a
  store behind an interface; Node 22+ ships an SQLite module. FTS5 if the viewer
  wants search.
- **Store interface** (`RunStore`, `PromptStore`, `ResultStore`) so a Postgres
  module can be added later without touching the domain.
- **Provider keys** encrypted at rest with a key from the environment.
- **Export bundle** per run: `manifest.json`, `pipeline.resolved.json`,
  `targets/<ref>.md` (rendered inputs), `channel_results/<channel>__<target>.md`,
  `raw_outputs/`, `validation/`, `synthesis.md`, `provenance.json`. Also
  per-prompt export: all versions as Markdown with YAML frontmatter.

### 7.8 Cadence and history

- URGE never schedules. `run_source: scheduled` is set by the caller (Routine,
  cron). `get_results` and `list_runs` filter on it.
- `input_source: result_history` channels (carried from v2) consume a batch of
  past Results selected by `input_filters`, so a weekly recap can later feed a
  monthly trend channel without a scheduler inside URGE.
- Calane's run diff (`RunDiffer`) is carried as a REST/CLI operation on two runs
  of the same pipeline; it is not an MCP tool.

### 7.9 Human viewer (later phase)

Read-only, server-rendered or a small static SPA. Browser access: the operator
pastes an API key once; it is stored in an `HttpOnly` cookie and checked by the
same `authorize()` as REST. No OIDC relying-party login, no fourth redirect URI,
no session store. An IdP-backed browser login may be added later as a module. Screens: prompt list; prompt page with
branch graph and versions; version diff; run list; run page showing targets ×
channels grid with provenance and the verdict; result page; export buttons.
No forms except "rate / star / note" on a result. Everything else is MCP/CLI.

### 7.10 Deployment

One Docker image, one process serving MCP HTTP + REST + viewer, one volume for
`urge.sqlite`, keys, and bundles. `render.yaml`/`fly.toml`/`compose.yaml`
examples. Health endpoint. No worker, no queue, no cron inside.

---

## 8. What is carried, rebuilt, dropped

| From | Carried as-is or adapted | Rebuilt from concept | Dropped |
|---|---|---|---|
| **Calane** (frozen; kernel carried as a standalone package inside v3, see §5) | TypeBox specs discipline; ChannelExecutor/PipelineExecutor (concurrency, retry, repair, topological levels); Ajv validation; content hashing; bundle exporter; SQLite store; OAuth resource-server auth; Streamable HTTP + stdio MCP transport; RunDiffer; TelemetrySink interface + OTel exporter as an opt-in package | — | federation, signing, canonical refs, A2A, ModelSelector, StatsQueries, filesystem/git registries (prompts live in the DB), delegated_agent placeholder, synthesis variants, carryForwardStrategy, governance docs |
| **URGE v2** | — | slug-addressed prompts; immutable versions; branches with fork point; `{{var}}` + `{{>ref}}` templating with strict mode and budgets; Result archive with provenance; client-side execution; `run_source`; `result_history` input; provider drivers (3 of 6) | React SPA, editor, visual composer, canvas, collections, share links, teams, namespaces, OAuth authorization server, DCR, refresh rotation, GitHub IdP, per-prompt API-key scoping, evaluation settings tables (evaluation becomes an ordinary pipeline), 31-tool MCP surface, Laravel |

---

## 9. Early plan (milestones, not sprints)

Each milestone ends with a runnable check the owner can perform with a real client.

**M0 — Skeleton.** Repo, pnpm workspace, TypeBox specs for every entity in §7.1,
SQLite store with migrations, application services with `authorize()`, stdio
MCP with `list_prompts`, `get_prompt`, `save_prompt`, `render_prompt`.
Check: from Claude Code as person A, create `escritorio`, fork two branches,
render each; as person B (`--as`), `list_prompts` does not show it until A marks
it `shared_with: [instance]`, after which B can render it and save a version.

**M1 — Runs with client execution.** Pipeline/Channel specs, `run`,
`complete_channel`, `get_run`, `list_runs`, `get_results`, `store_result`.
No providers yet: every channel routes to client.
Check: rebuild the escritório tree end to end from Claude Code, with Claude
doing the research and the judging, and every result linked to the run.

**M2 — Server execution.** Provider model and three drivers, router with
`needs_web` and `output_schema`, concurrency, retry, repair, Ajv.
Check: the `evaluation` pipeline (five JSON scorers) runs server-side on
OpenRouter; the `best-of` distiller runs server-side on Anthropic over three
client-produced candidates.

**M3 — Remote.** Streamable HTTP transport, RFC 9728 metadata, JWT validation
against one IdP, API keys, Docker image, volume, deploy to the container host.
Check: Claude.ai connector, ChatGPT connector, and Codex each complete M1's
flow against the hosted instance, and a second allowlisted person signs in
through Claude.ai and sees only shared prompts. This milestone is done only
when all pass; it is where the previous knot was.

**M4 — Mirror and export.** REST 1:1 with OpenAPI from TypeBox, CLI, run and
prompt export bundles, run diff.
Check: a cron job stores a `scheduled` result via REST; `export` of the
escritório run opens as readable Markdown.

**M5 — Viewer.** Read-only screens in §7.9.
Check: the owner re-enters the escritório run cold in a browser.

**M6 (optional) — History channels.** `result_history` input source and a
monthly trend pipeline over the weekly recap.

Tests: unit tests on services and renderer; executor tests with a mock provider
(Calane's); one end-to-end test per milestone check, offline, against the stdio
transport.

---

## 10. Left to the builder

- Package layout inside the monorepo, subject to one rule: the execution kernel is a package with no dependency on the prompt store (§5).
- HTTP framework (Calane used Fastify; keep unless there is a reason).
- SQLite driver (`node:sqlite` vs `better-sqlite3`) and migration mechanism.
- ID format (ULID suggested), hash canonicalization details.
- Exact TypeBox shapes, within §7.1.
- How `requested_via` is derived from MCP `initialize` client info per client.
- Allowlist administration surface (CLI only vs REST too) and the exact
  identity-claim matching rules.
- Rate-limit numbers, timeouts, concurrency defaults.
- Repository name and licence (Calane is AGPL-3.0; the owner chooses for v3).
- Which hosted and which self-hosted IdP the deploy guide documents.
- Viewer technology (M5), provided it adds no auth UI and no editor.

---

## 11. Risks and open questions for the owner

1. **IdP dependency.** Login for remote clients depends on a third-party identity
   provider. Trade-off accepted to avoid building an authorization server; the
   fallback if that proves painful is a minimal built-in AS as a later module,
   explicitly out of v3 scope.
2. **Client write-back reliability.** M3 is gated on all three clients completing
   `complete_channel`. If one client cannot call tools with large payloads, the
   fallback is server-side execution for that channel, which requires a provider
   with web search. This is why `needs_web` routing exists.
3. **Scope creep from Calane.** The carried code is roughly a quarter of Calane.
   Anything from the "Dropped" column needs a written reason before it enters.
4. **Reproducibility vs web.** A web-searching channel is not reproducible by
   construction. The run record stores what was produced and by whom, not a
   claim that re-running would match. The viewer should say so.

---

## Appendix A — Assessment of the existing repositories (2026-11-22, read-only)

**Most recent URGE repo:** urge-nt (last commit 2026-06-02, 70 commits) supersedes
urge-next (2026-03-27, 50 commits); urge-nt carries urge-next's later migrations.

**urge-nt (v2):** Laravel 12 + React 19; ~11.2k lines PHP, ~7.4k lines JSX, 22
models, 40 migrations, 54 test files (~511 methods). Core path: Prompt →
immutable Version (branches) → Result; TemplateEngine with includes and budgets;
pipelines with parallel/synthesis channels, client-side execution, and
`result_history` input; pipelines run synchronously in the request, "parallel"
channels run sequentially. MCP: 31 tools in one 1,998-line class; HTTP and stdio
have separate JSON-RPC switches; OAuth scopes enforced only on HTTP; per-prompt
API-key scope unenforced on MCP; client-run results cannot be linked to a
pipeline run. Auth: Sanctum → OAuth 2.1 (PKCE, DCR, refresh rotation, GitHub
IdP) → API key, cascade implemented twice. Security audit (June 2026): 77
findings, 4 Critical (IDORs), all Critical/High closed; open: DNS-rebind in SSRF
guard, CSP unverified in a browser, API keys bypass MCP scopes. CLAUDE.md drifts
from the code in several places. Settings is ~30% of the frontend.

**calane-claude:** TypeScript pnpm monorepo, ~9.3k lines; phases 1–7 signed off.
Entities: PipelineSpec (YAML), ChannelSpec (prompt path + JSON Schema path +
provider + dependsOn), ChannelResult (status enum, raw always kept), RunResult
with content hashes and git SHA. No per-prompt versioning, no includes, five
fixed template variables. Exactly 8 MCP tools (confirmed); `rerun_channel`
re-runs the whole pipeline. Real concurrency, retries, repair, bounded
recursion, cost policies, OTel, signed bundles, federation, A2A. Synthesis is a
channel; `variant` and `carryForwardStrategy` are labels without code. Defensible
kernel ≈ 2.5k lines. Governance docs are heavy relative to source.

**Overlap:** the two repos split one idea. URGE owns prompt memory and the human
loop; Calane owns reproducible execution and the run record. Both converged on
"parallel candidates → synthesis channel", which is the owner's distillation.
Calane's non-goal "no prompt-management product" exists to keep it from becoming
URGE.

## Appendix B — Launch-client auth facts (checked 2026-11-22)

- **Claude.ai custom connectors**: OAuth 2.0; Dynamic Client Registration
  supported; a custom client id and secret may be supplied for servers without
  DCR; callback `https://claude.ai/api/mcp/auth_callback`. Sources:
  https://www.claude.com/docs/connectors/building/authentication ,
  https://support.claude.com/en/articles/11503834 ,
  https://claude.com/docs/connectors/custom/remote-mcp
- **ChatGPT custom MCP servers**: remote HTTPS only (SSE or Streamable HTTP);
  auth options OAuth, none, or mixed; static credentials accepted; Client ID
  Metadata Documents supported when the AS advertises them. Source:
  https://developers.openai.com/api/docs/guides/custom-mcp-server
- **Codex**: Streamable HTTP servers with `codex mcp login`; supports CIMD and
  DCR; `--oauth-client-id` accepted, after which Codex shows the callback URL to
  register. Source: https://developers.openai.com/codex/mcp
- **MCP spec**: MCP servers are OAuth resource servers (RFC 9728 metadata);
  resource indicators (RFC 8707) required; the 2026-07-28 revision deprecates
  DCR in favour of CIMD. Source:
  https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization

Consequence: no launch client requires URGE to implement DCR or an authorization
server. Pre-registered clients at an external IdP satisfy all three.

## Appendix C — Builder-check resolutions (2026-11-22)

A fresh reviewer read the draft as a builder and asked six questions. Each was
settled as a designer's call and folded into the sections above; the owner may
override any of them.

| # | Question | Resolution | Where |
|---|---|---|---|
| 1 | Does `run` block until server work finishes, or poll? Can a run survive restarts and a completion days later? | Persisted state machine; async in-process work; `wait_ms` hybrid; `interrupted` on restart, explicit resume | §7.3 |
| 2 | Can a run target a Result (score one answer)? Which version does a multi-target synthesis Result carry? | Target refs grow with `result:<id>` and `run:<id>`; `prompt_version_id` nullable on synthesis; listed under every targeted prompt | §7.1 |
| 3 | Do scorer JSON blobs pollute prompt history? | `Result.kind` + channel `archive_as`; `get_results` defaults to `answer,synthesis` | §7.1 |
| 4 | How does the owner get a verdict when one client item never returns? | `finalize_run` MCP tool (14th); no expiry | §7.3, §7.5 |
| 5 | How does a browser authenticate to the viewer? | API key pasted once into an `HttpOnly` cookie; no OIDC RP login in v3 | §7.9 |
| 6 | Must the IdP issue `read`/`write` scopes? What identifies the operator? | Pinned identity claim, full access; scopes only on API keys | §7.6 |

## Appendix D — Audience revision (2026-11-22)

After the draft, the owner clarified that prompts and results must be shareable
with one other household member. Options weighed: (a) shared instance, everyone
sees everything; (b) one instance per person, share by export; (c) shared
instance, private by default, one visibility flag; (d) Teams built now. Owner
chose (c). Teams were declined for now because with two people a team is the
whole instance, and team semantics (membership changes, multi-team prompts,
edit vs read) would be decided with no case to test them against. To keep that
door open cheaply, visibility is an **audience reference** whose only v3 kind is
`instance`; a Team later is a new audience kind plus a membership table.
Consequences folded into §2, §3, §4, §7.1, §7.5, §7.6, §9, §10.
