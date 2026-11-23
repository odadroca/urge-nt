# URGE v3 — From-Scratch Design

Date: 2026-11-22 (revision 2, 2026-11-23, after independent audit)
Status: DRAFT for owner approval. Nothing is built until this document is approved.
Supersedes: nothing. urge-nt (v2) and calane-claude remain as they are; this describes a new repository.
Companion: `2026-11-22-urge-v3-design-audit.md` (four-lens audit; every disposition there is folded in here).

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
sharing need is one other household member. Calane, a separate kernel the owner
built afterwards, solved the parts URGE v2 does badly (reliable fan-out, linked
run records) and by contract refuses to do the parts URGE does well (prompt
memory). v3 puts the two halves in one process.

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

## 2. Decisions recorded from the owner interview (2026-11-22/23)

| # | Topic | Decision |
|---|---|---|
| 1 | Audience | Shared instance for a small allowlist of people (owner and family), private by default with one visibility flag. No teams. Others may self-host their own copy. |
| 2 | Hosting | Small container or VPS, **always-on** (paid tier or the owner's own always-on hardware). Language unconstrained. PHP not required. |
| 3 | Human UI | No full editor. Authoring happens through LLM clients over MCP. Read-only viewer later. |
| 4 | Data | Clean start. v2 instance stays up as an archive. No importer. |
| 5 | Launch clients | Claude Code, Claude.ai, ChatGPT/Codex. Mistral and others later. |
| 6 | Output contracts | Optional JSON Schema per channel. Prose is first-class. |
| 7 | Cadence | Passive. External schedulers trigger; URGE tags and stores. |
| 8 | Web access | Declared per channel. Server dispatches to providers whose search tool is configured; otherwise hands the channel to the client. |
| 9 | Build approach | B: one TypeScript repo absorbing Calane's kernel and rebuilding URGE's memory layer. Calane frozen. |
| 10 | Store | SQLite (designer's call, owner delegated). |
| 11 | Admin visibility | An admin manages the allowlist and providers and **cannot read other people's private records**. |
| 12 | Telemetry | Keep the small sink interface; the OTel exporter is parked (Appendix E2). |
| 13 | Audit | Four-lens independent audit run; all dispositions folded into this revision. |

Designer's calls the owner did not contradict (open to override):

- Launch providers: OpenRouter, Anthropic, one OpenAI-compatible driver.
- No outbound calls except configured providers and the configured IdP.
- Visibility is modelled as an **audience reference**; the only audience in v3 is
  `instance`. A Team later is one more audience kind plus a membership table.
- The paste-with-provenance path survives as one call (`store_result`).
- Tool hard cap raised from 16 to 20 after the audit; launch surface is 16.

---

## 3. Goals, non-goals, anti-goals

### Goals

1. An LLM client, given one instruction, can create a prompt, version it, fork
   branches, run the tree across configured models, and store a judged verdict,
   with every result linked to its run, branch, version, and provenance.
2. Reliable multi-model fan-out executed by the server when a provider is
   configured, with real concurrency, retries, and optional schema validation.
3. Client execution as a first-class mode: when the calling LLM is the best
   executor, URGE hands back the rendered channel and accepts the completion
   **into the same run**, even days later, across restarts.
4. A run record that is self-describing and exportable, with content hashes of
   every prompt, pipeline spec, and schema that shaped it.
5. One remote MCP server that Claude.ai, ChatGPT, and Codex each accept, with
   auth that is a thin resource-server layer and nothing more.
6. **Structural authorization**: no code path can read or mutate a record
   without an actor, enforced by the store API and by a build-failing test.
7. One-process deployment, always-on, with a single SQLite file and a tested
   backup/restore.

### Non-goals (v3; owner may revisit)

- Multi-tenancy. Teams, team CRUD, roles, per-user namespaces in slugs or URLs.
  Sharing between allowlisted people on one instance *is* in scope.
- An authorization server. URGE validates tokens; it never issues them.
- A prompt editor UI, visual composer, canvas, pipeline builder.
- An internal scheduler.
- Federation, signing, A2A, model selection, run diff, bounded recursion, OTel
  exporter (all parked or dropped; Appendix E).
- Importing v2 data. Collections, share links. Billing, marketplace, SaaS.

### Anti-goals

- A tool surface past ~20, or tools an LLM must guess between.
- Results without a link to run, branch, version, and provenance.
- MCP and REST disagreeing on lookup, authorization, or limits.
- A deployment that needs a second always-on process.
- Hardening work that exists only because surface area was added speculatively.
- Authorization that depends on a developer remembering to check.

---

## 4. Constraints

- **Small allowlist, private by default.** Every record has one owner. Visibility
  rules are in §7.1 and are the only rules; every read goes through them.
- **Actor-scoped data access.** Stores expose `get(actor, id)` and
  `list(actor, filter)` only. The single unscoped path is a `system` actor used
  by executor resume on startup. A test fails the build if any service or store
  method lacks an `Actor` parameter.
- **TypeScript / Node 24 LTS.** One Node version pinned in `engines`, the
  Dockerfile, and the platform config.
- **TypeBox is the single schema source** for internal contracts, REST
  validation, MCP tool descriptors, OpenAPI, and the shape of user-authored
  output schemas. No second schema library.
- **Prose is first-class.** Validation runs only when a channel declares
  `output_schema`. Nothing forces prose into JSON.
- **No outbound calls except to configured LLM providers and the configured IdP.**
  Backups are pulled, never pushed.
- **Web access is a declared capability**, routed per channel at run time, never
  silently.
- **Stateless Streamable HTTP.** No server-side MCP session state; the actor is
  resolved per HTTP request from the bearer token or API key.
- **Always-on host.** Background server work must not be killed by platform idling.
- **The v2 instance and the Calane repo are not modified.**

---

## 5. Approaches considered

**A. URGE as a thin layer over Calane, two repos.** Rejected: Calane's channels
reference prompts by file path with no variables or includes, so either its
frozen contract breaks or the run record loses template lineage. Two MCP
servers to keep consistent; the escritório linkage already broke across a
process boundary.

**B. One new repo absorbing Calane's kernel and URGE's memory.** Chosen.
Honest carry accounting after audit: roughly 800–1,000 lines of Calane are
reusable as-is (topological execution plan, retry/repair loop, Ajv wrapper,
hashing, TypeBox spec discipline, telemetry sink interface, RFC 9728 metadata
and JWKS verification skeleton). The validator, renderer, bundle exporter, HTTP
transport, and auth code are **adapted** with specific changes listed in §7.3,
§7.5, §7.6. The run loop and the store are **rebuilt** (§7.3: persisted state
machine). Cost: imported code brings its shape; each piece is re-justified.

**C. Concept-only rewrite.** Rejected: slowest, discards a tested executor core.

**Calane's fate (owner decision):** v3 is the single tool. Calane is frozen as
an archive and reference. The separation that survives is a **package boundary,
not a runtime switch**: the execution kernel is its own package inside the v3
monorepo with no dependency on the prompt store. There is no kernel-only mode.

---

## 6. The system in one picture

```
Claude Code (stdio, --as person) ─┐
Claude.ai (HTTP, OAuth) ──────────┤                     ┌─ Memory ────────────────────────┐
ChatGPT (HTTP, OAuth) ────────────┼─► MCP (stateless) ─┐ │ Person · Prompt · Branch        │
Codex (HTTP, OAuth) ──────────────┘                    │ │ Version · Provider · Pipeline   │
                                                       ├─► Application services ───┤ Run · ChannelResult · Result    │
cron / Routine / CLI ─► REST (mirror, API key) ────────┘   (Actor on every call;   │ Provenance · VisibilityChange   │
                                                           actor-scoped stores)    └─────────────────────────────────┘
                                                                  │
                                                                  ▼
                                           ┌─ Execution kernel (package, no store dependency) ─┐
                                           │ render → route (server|client) → provider call   │
                                           │ → optional parse+validate → repair → synthesis   │
                                           └───────────────────────────────────────────────────┘
                                                                  │
                                                 SQLite (WAL) · backups · export bundles
```

One rule above all: **MCP, REST, and CLI are thin adapters over the same
application services.** No adapter looks up a record, resolves a ref, checks
authorization, or drives a run on its own.

---

## 7. Design

### 7.1 Domain model

All entities carry `id` (ULID), `created_at`, `owner_id` (Person).

**Person** — `display_name`, `identities[]` of `(iss, sub)` pairs (email is never
an identity; it may be shown as a label), `is_admin`, `created_at`. Admins manage
the allowlist and providers. **Admins have no read-through on private records.**
Configured by CLI or REST; no sign-up.

**Visibility** (field on Prompt and Pipeline) — `private` or
`shared_with: [audience...]`; the only audience kind in v3 is `instance`.

**Visibility rules (complete; `authorize()` implements exactly these):**
1. A Prompt, its Branches and Versions: visible to the owner, or to everyone on
   the instance when `shared_with` includes `instance`.
2. A Pipeline: same rule as Prompt.
3. A Run: visible to its `requested_by`, or to anyone who can see the pipeline
   **and every target**. A run over mixed-visibility targets is requester-only.
4. A Result with `run_id`: takes its Run's visibility. A Result without `run_id`:
   visible to its `recorded_by`, or to anyone who can see its prompt version.
5. **A recorder always sees what they recorded**, regardless of later visibility
   changes by the prompt owner.
6. Writes: anyone who can see a shared Prompt may add Versions, Branches, and
   Results to it and run pipelines against it. Delete, archive, rename, and
   visibility changes are owner-only. `complete_channel`, `finalize_run`, and
   `run_pipeline(resume=…)` require actor = the run's requester.
7. Invisible and missing are indistinguishable: uniform 404. Exception: a
   `save_prompt` slug collision returns "slug taken" (accepted existence leak at
   household scale).
8. Providers are instance-wide, admin-managed; keys are never returned.

**VisibilityChange** — audit row: `entity`, `id`, `from`, `to`, `by`, `at`.

**Prompt** — `slug` (instance-unique; **tombstoned on delete, never reusable**),
`name`, `type` (`prompt` | `fragment`), `description`, `tags[]`, `visibility`,
`archived_at?`. An include of a prompt the reader cannot see fails the render
with a clear error; sharing a composite does not share its fragments.

**Branch** — `prompt_id`, `name`, `description` (why this branch exists),
`head_version_id`, `forked_from_version_id?`, `is_default`, `created_by`.

**Version** — immutable. `prompt_id`, `branch_id`, `number` (global per prompt),
`branch_number` (display only), `content`, `commit_message?`, `variables[]`,
`includes[]` (resolved to **prompt ids** at save time, plus the ref text),
`content_hash`, `variable_defaults{}?`, `created_by`.

**Prompt ref grammar (one grammar everywhere, URL-safe):** `slug`,
`slug@branch`, `slug~7`, `slug@branch~7`. `~N` is **always the global version
number**. Resolution: explicit `~N` → branch head → default-branch head.
Display may add the branch-local number: `escritorio@sem-banho~7 (#2)`.

**Target ref** — what a Run may target:
- a prompt ref → `{{input}}` is the rendered prompt;
- `result:<id>` → `{{input}}` is that Result's text; `{{target.prompt}}` is the
  rendered prompt that produced it when known;
- `run:<id>[?kind=answer]` → one target per archive Result of that run;
  `synthesis` excluded by default;
- `results:<prompt ref>?since&limit&run_source&kind` → **one** target whose
  `{{input}}` is a serialized batch of matching Results (this replaces v2's
  `result_history` input source). Every selector is resolved through the actor's
  visibility.

**Provider** — `slug`, `driver` (`openrouter` | `anthropic` | `openai_compatible`),
`model`, `endpoint?` (validated: https only, hostname allowlist, no private IPs,
checked at save and at dispatch), `api_key` (encrypted at rest, key in env),
`capabilities{ web_search, json_schema }`, `active`.

**Pipeline** — `slug`, `name`, `description`, `visibility`, `channels[]`,
`synthesis?`, `defaults{ retry, repair, concurrency }`, `spec_hash`.
`save_pipeline` replaces the spec and takes `expected_hash` to prevent silent
overwrite between two people. A Run **snapshots the resolved spec**; later edits
never change a run in flight or its record.

**Channel** — `id`, `role_label`, `system_prompt` (may include fragments and
run-context variables), `execution` (`server` | `client` | `auto`),
`provider_ref?`, `intended_executor?` (free text shown on pending items, e.g.
"chatgpt-5.4-thinking", so the calling LLM knows a slot is not for it),
`needs_web`, `output_schema?` (inline JSON Schema; remote `$ref` disabled),
`depends_on[]`, `retry?`, `repair?`, `archive_as` (`answer` default | `score` |
`synthesis` | `none`).

**Run** — `pipeline_id`, `pipeline_snapshot` (resolved spec JSON), `spec_hash`,
`targets[]` (resolved: ref text, version id, content hash), `variables{}`,
`status` (`running` | `awaiting_client` | `completed` | `partial` | `failed`),
`run_source` (`manual` | `scheduled`), `requested_by` (Person),
`requested_via` (derived server-side: token `client_id`/`azp`, API-key name, or
`cli`), `started_at`, `finished_at?`, `finalized_by?`, `synthesis_result_id?`.

**ChannelResult** — one per (run, channel, target); `item_id` (stable, returned
on pending items). `status` (`pending_server` | `running` | `pending_client` |
`ok` | `invalid_json` | `schema_error` | `refused` | `timeout` | `error` |
`interrupted` | `skipped`), `skipped_reason?`, `raw_output` (always kept),
`parsed_output?` (only when a schema exists), `validation_errors?`,
`repair_attempted`, `rendered_prompt_hash`, `system_prompt_hash`,
`latency_ms?`, `usage?`, Provenance.

**Result** — the archive row. `kind` (`answer` | `score` | `synthesis`),
`prompt_version_id?` (null for synthesis over several targets), `run_id?`,
`channel_result_id?`, `scored_result_id?` (for `score`), `text`,
`variables{}?`, `rendered_hash?`, `rating?`, `starred`, `notes?`, `run_source`,
`recorded_by`, Provenance. `get_results` returns `answer` and `synthesis` by
default; a synthesis is listed under every prompt its run targeted, subject to
rule 4.

**Provenance** — who produced the text and who recorded it are separate.
`executed_by` (`server` | `client` default | `external`), `provider`, `model`
(always the producer), `web_used?`, `tokens_in?`, `tokens_out?`,
`produced_at?`, `source_url?`, `self_reported: bool` (true for anything the
client asserted rather than the server observed), `recorded_via`,
`recorded_by`, `recorded_at`. Example: "record this result for
`escritorio@sem-banho`, sourced via web from ChatGPT 5.4 thinking" →
`executed_by: external, provider: openai, model: chatgpt-5.4-thinking,
web_used: true, self_reported: true`.

### 7.2 Templating

- `{{name}}` user variable. Strict by default; `strict=false` for preview.
- `{{>slug}}`, `{{>slug@branch}}`, `{{>slug~N}}` includes, recursive, cycle
  detection, depth cap (10), expansion budget. Includes are resolved to prompt
  ids when a version is saved, so a tombstoned or re-created slug can never be
  substituted later.
- **Values are inert.** Substitution is single-pass; variable values, `{{input}}`
  and `{{channel_results}}` are never re-scanned for syntax. A web-sourced answer
  containing `{{>private-fragment}}` is text.
- **Fragment defaults** apply only to variables the fragment itself introduces
  (v2 TPL-05 rule kept).
- Run-context variables in channel system prompts: `{{input}}`,
  `{{channel_results}}`, `{{channel_results.<id>.raw|parsed}}`,
  `{{previous_synthesis}}`, `{{run_id}}`, `{{target.ref}}`, `{{target.branch}}`,
  `{{target.prompt}}`. Prose channels render as `raw`; `parsed` is null without
  a schema. (Calane's renderer is adapted: it had one template, not a
  system/user split, so the provider adapter interface gains `system`.)
- Composition: fragments are reusable pieces; late binding by default, early
  binding with `~N`; `get_prompt` returns `used_by`; `save_prompt` on a fragment
  reports which composites' default renders shift.
- Rendering is a pure function of (version content, resolved includes,
  variables); every render records participating version ids and a hash.

### 7.3 Execution

**Kernel (package, no store dependency).** Carried from Calane: topological
levels from `depends_on`, concurrency caps (global and per provider), transient
retry with exponential backoff, schema-repair re-prompting, Ajv validation, raw
output always kept. **Adapted:** validation only when `output_schema` is set;
otherwise `parsed_output = null`, status `ok`, `{{channel_results}}` renders
raw. Synthesis is a normal channel over the level's results.

**Routing per channel×target** (`execution: auto`): server if the channel's
provider is active and (`needs_web` is false or the provider has `web_search`);
otherwise `pending_client` with the rendered system and user prompt returned.
`server` fails loudly instead of falling back; `client` never dispatches.

**Dependencies resolve within one target.** A level for a target is released
when every prior-level item for that same target is terminal. Pending client
items park the DAG for that target only.

**Persisted state machine.** A run is rows, not a function. Transition table
for a ChannelResult:

| From | Event | To |
|---|---|---|
| (new) | plan | `pending_server` or `pending_client` |
| `pending_server` | dispatch | `running` |
| `running` | provider ok (+ validation ok or no schema) | `ok` |
| `running` | invalid JSON / schema error after repairs | `invalid_json` / `schema_error` |
| `running` | refusal / timeout / error after retries | `refused` / `timeout` / `error` |
| `running` | process restart | `interrupted` |
| `pending_client` | `complete_channel(item_id)` | `ok` (or `invalid_json`/`schema_error` if a schema exists; no repair for client items) |
| `pending_client` | `finalize_run` | `skipped` (reason `finalized`) |
| any terminal | `complete_channel` again | no-op, returns existing result |
| `interrupted` / `error` / `timeout` | `run_pipeline(resume, retry_failed)` | `pending_server` |

Run status is derived: `running` while any item is `pending_server`/`running`;
`awaiting_client` while none is running and some are `pending_client`;
`completed` when all terminal and all `ok`; `partial` when all terminal with
some not `ok` (including `skipped`); `failed` when synthesis could not run.
Dispatch is owned by one in-process coordinator; `complete_channel`,
`run_pipeline(resume)`, and polls only enqueue work for it, so overlapping calls
cannot double-dispatch. On startup the coordinator marks `running` items
`interrupted`; nothing is re-run silently, and `get_run` carries a resume hint.

**Waiting.** `run_pipeline`, `complete_channel`, `finalize_run`, and `get_run`
accept `wait_ms` (default 20 000, cap 45 000, below Fly's fixed 60 s proxy
timeout and the 60 s drops clients report). The server sends SSE progress
notifications while waiting so bytes flow. Clients that cannot sleep poll
`get_run` with `wait_ms`.

**Client completion.** `complete_channel(run_id, item_id, output, provenance?)`
records the result into the run. `provenance.executed_by` defaults to
`client`; `recorded_via` is derived server-side. When every parallel item is
terminal, synthesis runs server-side if it routes there, otherwise one more
pending item is returned.

**Limits.** Max channel×target items per run (configurable, default 50); body
size caps on `output`/`text`; per-run cost ceiling using provider usage
reports; per-actor rate limiting in the adapter layer for every auth kind.

### 7.4 The owner's workflow, end to end

"Write a research prompt for X, save it as `escritorio`, fork branches for
`sem-isolamento` and `sem-banho`, run all branches against the configured
models, then judge."

1. `save_prompt(slug="escritorio", content, commit_message)` → `escritorio~1` on `main`.
2. `save_prompt(slug="escritorio", branch="sem-isolamento", fork_from="escritorio~1", content)` → `~2`.
3. Same for `sem-banho` → `~3`.
4. `run_pipeline(pipeline="research-fanout", targets=["escritorio@main", "escritorio@sem-isolamento", "escritorio@sem-banho"], variables={…})`.
   The pipeline has channel `research` (`needs_web: true`, `execution: auto`,
   provider `openrouter` without web) and synthesis `judge` (`execution: auto`,
   provider `anthropic`). Router: three `pending_client` items with `item_id`s
   and rendered prompts; `judge` routes to server.
5. The client executes the three research prompts with its own web search and
   calls `complete_channel(run_id, item_id, output)` three times.
6. The coordinator runs `judge` over the three results, stores the synthesis
   Result, marks the run `completed`; the third `complete_channel` call returns
   the finished snapshot within `wait_ms`, or the client polls `get_run`.
7. `update_result(id, starred=true)` on the winner.
8. Months later: `get_run(run_id)` or `get_results("escritorio")` returns the
   tree, every branch's answer, who produced it, and the verdict; REST/CLI export
   writes a Markdown bundle.

If one external slot never comes back: `finalize_run(run_id)` → judge runs over
what exists → `partial`. If the judge times out: `run_pipeline(resume=run_id,
retry_failed=true)` re-runs only the judge.

### 7.5 Machine surface

**Application services** (`PromptService`, `RenderService`, `PipelineService`,
`RunService`, `ResultService`, `ProviderService`, `PersonService`,
`ExportService`) take an `Actor` on every method and call actor-scoped stores.
`Actor` = `person(id, is_admin) | key(person_id, read|write) | system`.

**MCP tools** (16 at launch, hard cap 20):

| Tool | Purpose |
|---|---|
| `list_prompts(type?, tag?, search?, scope?: mine\|shared\|all)` | browse what the actor can see |
| `get_prompt(ref)` | content, metadata, branches, `used_by`, unshared-include warnings |
| `save_prompt(slug, content, commit_message?, branch?, fork_from?, type?, tags?, visibility?)` | create prompt / new version / new branch; defined error matrix (§10) |
| `update_prompt(slug, name?, description?, tags?, visibility?, archived?)` | metadata without a new version; visibility owner-only |
| `render_prompt(ref, variables, strict?)` | rendered text, `resolved_ref`, `rendered_hash`, used/missing variables, resolved includes |
| `list_pipelines(scope?)` | browse |
| `get_pipeline(slug)` | spec, `spec_hash`, routing preview against current providers |
| `save_pipeline(slug, spec, expected_hash?, visibility?)` | create or replace |
| `run_pipeline(pipeline?, targets?, variables?, run_source?, resume?, retry_failed?, include_rendered?, wait_ms?)` | start or resume; returns snapshot + pending items (`item_id`, `intended_executor`, prompts) |
| `complete_channel(run_id, item_id, output, provenance?, wait_ms?)` | client hands back a result into its run |
| `finalize_run(run_id, wait_ms?)` | skip outstanding client items, synthesize, end `partial` |
| `get_run(run_id, include_rendered?, wait_ms?)` | full run record; the tool that is polled |
| `list_runs(pipeline?, target?, status?, run_source?, since?)` | browse runs |
| `get_results(ref, kind?, run_source?, since?, limit?, starred?)` | history for a prompt |
| `store_result(ref, text, provenance, variables?, rendered_hash?, notes?, rating?, run_source?)` | ad-hoc / paste path |
| `update_result(id, rating?, starred?, notes?)` | act on a verdict |

**MCP resources**: `urge://prompts`, `urge://prompts/{ref}`, `urge://runs/{id}`.

**Transports**: stdio (`--as <person>`, required when the allowlist has more
than one entry) and **stateless** Streamable HTTP at `/mcp`. Both call one
JSON-RPC dispatcher. Over HTTP, authentication happens at the HTTP layer before
any JSON-RPC dispatch, `initialize` and `tools/list` included; header-only
bearer; no token in `params._meta`.

**REST** mirrors the services 1:1 with OpenAPI 3.1 generated from TypeBox:
`/prompts`, `/prompts/{ref}`, `/pipelines`, `/runs`, `/results`, `/providers`,
`/people`, `/export/...`, `/export/backup` (admin key), `/health`.

**CLI** (`urge`) is thin over REST only (no in-process mode): `serve`,
`mcp-stdio`, `doctor`, `backup`, `person add|list`, `key create`, `provider
add|list`, `prompt get|save|render`, `run`, `complete`, `results`, `export`.

### 7.6 Authentication and authorization

- **URGE is an OAuth 2.1 resource server, never an authorization server.** It
  publishes RFC 9728 Protected Resource Metadata (with `scopes_supported`)
  pointing at an external IdP, validates bearer JWTs (RS256 and ES256) against
  the IdP's JWKS with pinned `iss`, required `exp`, clock skew, bounded JWKS
  refresh with timeout, and checks `aud` against the configured audience. An
  introspection fallback exists for IdPs that issue opaque tokens.
- **Identity is `(iss, sub)` → Person.** Unknown identities are rejected. No sign-up.
- **IdP requirements, stated honestly:** the IdP must issue JWT access tokens
  with a configurable audience. On Auth0 and Entra that means registering an API
  (and, on Auth0, a default audience, because at least one connector omits the
  RFC 8707 `resource` parameter). The deploy guide documents this step for one
  hosted and one self-hosted IdP, lists the redirect URIs **as each connector
  reveals them** (ChatGPT may use a per-connector callback; Codex uses a
  localhost port that must be fixed with `mcp_oauth_callback_port`), and notes
  that the IdP must allow `localhost` redirects for Codex.
- **Client registration happens at the IdP.** Pre-registered client ids satisfy
  Claude.ai (custom client id and secret), ChatGPT (static credentials), and
  Codex (`--oauth-client-id`). DCR and CIMD are the IdP's business.
- **API keys** (`urge_` prefix, sha256 at rest) belong to a Person, carry
  `read` or `write`, never admin. Used by cron, CLI, the viewer cookie, and the
  backup pull.
- **Rate limiting** per actor in the adapter layer, for OAuth and key callers alike.
- **Required changes to carried Calane auth code:** refuse to start the HTTP
  transport without an auth config (no fail-open); authenticate before dispatch;
  header-only bearer; require `exp`; bound JWKS refetches; no in-memory MCP
  sessions.
- **Error redaction**: provider error bodies are size-capped and redacted
  before reaching any client or log.

### 7.7 Storage

- **SQLite, WAL mode, one file**, `better-sqlite3` on Node 24 LTS,
  `busy_timeout = 5000`. Synchronous driver means in-process async runs
  serialize safely; the CLI never opens the file while the server runs (it uses
  REST). FTS5 for viewer search with quoted queries.
- **Store interface** per entity, actor-scoped; a Postgres module later is an
  addition.
- **Provider keys** encrypted at rest with a key from the environment, **never
  on the volume**; the key is recorded in the operator's password manager.
- **Backup**: `urge backup` runs `VACUUM INTO /data/backups/<ts>.sqlite`;
  `GET /export/backup` (admin key) lets a home cron **pull** it. Restore removes
  stale `-wal`/`-shm` files. Backups are plaintext prompts and results; the
  guide says so.
- **Export bundle** per run: `manifest.json`, `pipeline.snapshot.json`,
  `targets/`, `channel_results/`, `raw_outputs/`, `validation/`, `synthesis.md`,
  `provenance.json`, filenames sanitized. Per-prompt export: all versions as
  Markdown with YAML frontmatter.

### 7.8 Cadence and history

URGE never schedules. `run_source: scheduled` is set by the caller. History
feeds a channel through the `results:<ref>?…` target selector (§7.1), resolved
through the actor's visibility; this is the M6 feature and needs no second
execution path.

### 7.9 Human viewer (M5)

Read-only, server-rendered or small static SPA. Browser access: the operator
pastes a `read` API key once; stored in an `HttpOnly`, `SameSite=Strict` cookie
honoured only on viewer routes; the three write actions (rate, star, note)
require a custom header; CSP set; Markdown rendered sanitized. Screens: prompt
list; prompt page with branch graph and descriptions; version diff; run list;
run page with targets × channels grid, provenance, verdict; result page; export
buttons.

### 7.10 Deployment and operations

One Docker image, one process (MCP HTTP + REST + viewer), one volume for
`urge.sqlite` and `backups/`. **Always-on**: a paid tier that does not sleep
(e.g. Render Starter, Fly with one machine kept running) or the owner's own
always-on hardware behind an HTTPS tunnel (the guide documents one tunnel
option). `/health`. `urge doctor` checks env, DB opens, JWKS reachable, one
minimal call per active provider; it is the first command after a long pause.
Single Node pin. No worker, no queue, no cron inside.

### 7.11 Security hardening carried from the v2 audit

Theme A (authorization): §4 actor-scoped stores. Theme B (identity): §7.6.
Theme C (SSRF and leakage): endpoint allowlist at save and dispatch, no private
IPs, Ajv remote `$ref` disabled, central error redaction. Theme D (DoS):
include depth and expansion budgets, per-actor rate limits, item and body caps,
per-run cost ceiling. Theme E (transport): CSP, HSTS, no-cache on API, stateless
sessions. OTel spans, if ever enabled, never carry prompt or output text.

---

## 8. What is carried, adapted, rebuilt, dropped

| From | Carried as-is (~800–1,000 lines) | Adapted | Rebuilt from concept | Dropped or parked |
|---|---|---|---|---|
| **Calane** (frozen) | TypeBox spec discipline; topological execution plan; retry/repair loop; Ajv wrapper; content hashing; TelemetrySink interface; RFC 9728 metadata and JWKS verification skeleton | PromptRenderer (system/user split, prose path); ChannelExecutor (optional validation); bundle exporter (prose, new layout); Streamable HTTP transport (stateless, auth-before-dispatch); auth (fail-closed, `exp`, bounded JWKS) | run loop as persisted state machine; store as actor-scoped per-entity interfaces | federation, signing, canonical refs, A2A, ModelSelector, StatsQueries, RunDiffer, bounded recursion, OTel exporter (parked), registries, delegated_agent, synthesis variants, carryForwardStrategy |
| **URGE v2** | — | — | slug-addressed prompts; immutable versions; branches with fork point and description; templating with strict mode, budgets, inert values; Result archive with provenance; client-side execution; `run_source`; history as a target selector; 3 provider drivers | React SPA, editor, canvas, collections, share links, teams, namespaces, OAuth AS, DCR, refresh rotation, GitHub IdP, per-prompt key scoping, evaluation tables, 31-tool surface, Laravel |

---

## 9. Milestones

Each ends with a check the owner performs with a real client.

**M0 — Skeleton.** Repo, pnpm workspace, TypeBox specs for every entity in
§7.1, SQLite with migrations, actor-scoped stores, services with `Actor`, the
build-failing "every method has an Actor" test, Person/allowlist CLI, stdio MCP
with `list_prompts`, `get_prompt`, `save_prompt`, `update_prompt`,
`render_prompt`.
Check: as person A, create `escritorio`, fork two branches, render each; as
person B (`--as`), nothing is visible until A shares it; then B renders and
saves a version; A sees B's version; A flips to private; B still sees B's own
version and nothing else.

**M1 — Runs with client execution and the persisted state machine.**
Pipeline/Channel specs, snapshot on run, `run_pipeline`, `complete_channel`
with `item_id`, `finalize_run`, `get_run`, `list_runs`, `get_results`,
`store_result`, `update_result`, `interrupted` and resume, `wait_ms`. No
providers yet.
Check: rebuild the escritório tree end to end from Claude Code with Claude doing
research and judging; restart the process between two completions and finish the
run; finalize a run with one item outstanding.

**M2 — Server execution.** Providers and three drivers, router with `needs_web`
and `output_schema`, concurrency, retry, repair, Ajv, limits, endpoint
validation, error redaction.
Check: `evaluation` runs server-side on OpenRouter over `result:<id>`; `best-of`
runs server-side on Anthropic over `run:<id>`; a prose channel and a schema
channel in one pipeline both end `ok`.

**M3a — Hosted, keyed.** Stateless HTTP transport, API keys, Docker, volume,
always-on deploy, `/health`, `urge backup` and `/export/backup`, per-run export,
minimal REST (`/results`, `/runs`, `/export`).
Check: Claude Code over HTTP with an API key completes M1's flow against the
hosted instance; a restart mid-run survives; a backup restores on a laptop.

**M3b — IdP and Claude.ai.** RFC 9728, JWT validation, IdP setup guide, second
person.
Check: raw-token `curl` first; then Claude.ai connector completes M1's flow; a
second allowlisted person signs in through Claude.ai and sees only shared prompts.

**M3c — ChatGPT.** Check: ChatGPT connector completes M1's flow including
`complete_channel`.

**M3d — Codex.** Check: Codex completes M1's flow.

**M4 — Full REST mirror, CLI, exports.** OpenAPI from TypeBox, `urge` CLI over
REST, prompt export, `urge doctor`.
Check: a cron job stores a `scheduled` result via REST with a `read`-only key
refused and a `write` key accepted; the escritório run exports as readable Markdown.

**M5 — Viewer.** Check: the owner re-enters the escritório run cold in a browser.

**M6 — History selector.** `results:<ref>?…` targets; a monthly trend pipeline
over the weekly recap.

Tests: unit tests on services, renderer, visibility rules (owner, sharer,
stranger triples), state machine transitions; executor tests with the mock
provider; one offline end-to-end test per milestone check over stdio.

---

## 10. Left to the builder

- Package layout, subject to: the kernel package has no dependency on the store.
- HTTP framework (Fastify unless there is a reason).
- Migration mechanism; exact TypeBox shapes within §7.1; hash canonicalization.
- `save_prompt` error matrix: `branch` without `fork_from` on a new branch;
  `type` on an existing prompt; `tags` replace vs merge; collision with a
  tombstoned slug.
- Coordinator implementation (in-process queue), SSE progress format.
- Rate-limit numbers, timeouts, concurrency defaults, cost-ceiling source.
- Repository name and licence (Calane is AGPL-3.0).
- Which hosted and which self-hosted IdP the deploy guide documents; which tunnel.
- Viewer technology (M5), within §7.9.

---

## 11. Risks

1. **IdP and connector drift.** Client auth requirements changed between the
   v2 build and this audit and will change again. M3b–d are gated per client so
   one regression does not block the others.
2. **Client write-back payloads.** If a client cannot call `complete_channel`
   with large outputs, the fallback is a web-capable server provider for that
   channel; `needs_web` routing exists for this.
3. **Scope creep from Calane.** Anything in the dropped column needs a written
   reason before it enters.
4. **Reproducibility vs web.** A web-searching channel is not reproducible. The
   record stores what was produced and by whom; the viewer says so.
5. **Shared provider spend.** One person can spend another's key; the per-run
   cost ceiling and per-actor limits bound it, they do not allocate it.

---

## Appendix A — Assessment of the existing repositories (2026-11-22, read-only)

**Most recent URGE repo:** urge-nt (last commit 2026-06-02, 70 commits)
supersedes urge-next (2026-03-27, 50 commits); urge-nt carries urge-next's later
migrations.

**urge-nt (v2):** Laravel 12 + React 19; ~11.2k lines PHP, ~7.4k lines JSX, 22
models, 40 migrations, 54 test files (~511 methods). Core path: Prompt →
immutable Version (branches) → Result; TemplateEngine with includes and budgets;
pipelines with parallel/synthesis channels, client-side execution, and
`result_history` input; pipelines run synchronously in the request. MCP: 31
tools in one 1,998-line class; two JSON-RPC switches; OAuth scopes enforced only
on HTTP; per-prompt API-key scope unenforced on MCP; client-run results not
linked to a pipeline run. Auth cascade implemented twice. Security audit (June
2026): 77 findings, 4 Critical (IDORs), all Critical/High closed; open: DNS
rebind in SSRF guard, CSP unverified, API keys bypass MCP scopes. CLAUDE.md
drifts from the code.

**calane-claude:** TypeScript pnpm monorepo, ~9.3k lines; phases 1–7 signed off.
PipelineSpec (YAML), ChannelSpec (prompt path + JSON Schema path + provider +
dependsOn), ChannelResult (status enum, raw always kept), RunResult with content
hashes and git SHA. No per-prompt versioning, no includes, five fixed template
variables, one template (no system/user split), JSON parsing unconditional.
Exactly 8 MCP tools; `rerun_channel` re-runs the whole pipeline. Real
concurrency, retries, repair, bounded recursion, cost policies, OTel, signed
bundles, federation, A2A. Auth fails open when unconfigured; MCP sessions held
in memory. Defensible, reusable-as-is core ≈ 800–1,000 lines.

**Overlap:** the two repos split one idea. URGE owns prompt memory and the human
loop; Calane owns reproducible execution and the run record. Both converged on
"parallel candidates → synthesis channel", which is the owner's distillation.

## Appendix B — Launch-client auth facts (checked 2026-11-22/23)

- **Claude.ai custom connectors**: OAuth 2.0; DCR supported; custom client id
  and secret accepted; callback `https://claude.ai/api/mcp/auth_callback`.
  https://www.claude.com/docs/connectors/building/authentication ,
  https://support.claude.com/en/articles/11503834
- **ChatGPT custom MCP servers**: remote HTTPS only; OAuth, none, or mixed;
  static credentials accepted; CIMD when advertised; callback may be a fixed
  legacy URI or a per-connector `https://chatgpt.com/connector/oauth/{id}`;
  reports that the connector omits the RFC 8707 `resource` parameter.
  https://developers.openai.com/api/docs/guides/custom-mcp-server ,
  https://developers.openai.com/apps-sdk/build/auth
- **Codex**: Streamable HTTP with `codex mcp login`; CIMD and DCR; client id
  configurable; localhost callback port fixed via `mcp_oauth_callback_port`.
  https://developers.openai.com/codex/mcp
- **IdPs**: Auth0 issues JWT access tokens only with a registered API/audience
  (or a tenant default audience); Entra requires "Expose an API".
  https://support.auth0.com/center/s/article/opaque-versus-jwt-access-token ,
  https://learn.microsoft.com/entra/identity-platform/claims-validation
- **MCP spec**: servers are OAuth resource servers (RFC 9728); RFC 8707 resource
  indicators required of clients; 2026-07-28 revision deprecates DCR for CIMD.
  https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization
- **Platform limits**: Fly 60 s idle proxy timeout (fixed); Render free tier
  spins down and has no disk; both snapshot volumes but warn against
  disk-restore for databases. https://community.fly.io/t/60-second-timeout-for-http-server/1243 ,
  https://render.com/docs/free , https://render.com/docs/disks
- **SQLite**: `node:sqlite` experimental on Node 22; WAL backup requires
  `VACUUM INTO` or the backup API. https://sqlite.org/backup.html

Consequence: no launch client requires URGE to implement DCR or an
authorization server; the IdP must be configured with an API/audience; redirect
URIs are discovered per connector.

## Appendix C — Builder-check resolutions (2026-11-22)

| # | Question | Resolution |
|---|---|---|
| 1 | Block or poll? Survive restarts? | Persisted state machine; `wait_ms` hybrid; `interrupted` + explicit resume (§7.3) |
| 2 | Target a Result? Version of a multi-target synthesis? | `result:` / `run:` / `results:` targets; `prompt_version_id` nullable (§7.1) |
| 3 | Scorer JSON in prompt history? | `Result.kind` + `archive_as` (§7.1) |
| 4 | Verdict when a client item never returns? | `finalize_run` (§7.5) |
| 5 | Browser auth to the viewer? | API key cookie, hardened (§7.9) |
| 6 | Must the IdP issue scopes? | Identity-pinned tokens; scopes on API keys only (§7.6) |

## Appendix D — Audience revision (2026-11-22)

Options weighed: shared-everything; one instance each; shared instance private
by default with one flag; Teams now. Owner chose private-by-default with one
flag. Teams declined: with two people a team is the whole instance, and team
semantics would be decided with no case to test them. Visibility is an audience
reference whose only v3 kind is `instance`.

## Appendix E — Parked ideas (owner-raised or audit-raised, not scheduled)

**E1 — External-run envelope.** Wrap a rendered prompt for hand execution on
another model in a contract the model fills (model, version, date, web used),
parsed by `store_result`; itself a versioned fragment; self-reports marked
`self_reported`. Earliest after M4.

**E2 — OTel exporter.** The `TelemetrySink` interface is in the kernel; the
exporter package is parked until a run needs tracing. Spans must never carry
prompt or output text.

**E3 — Run diff.** Calane's `RunDiffer` is bound to the old `RunResult` shape;
a v3 diff over two runs of one pipeline is parked until the viewer wants it.

**E4 — Bounded recursion.** Parked; no v3 workload needs it.

## Appendix F — Audit

See `2026-11-22-urge-v3-design-audit.md`. Owner decisions taken on it
(2026-11-23): admins cannot read private records; hosting is always-on; the OTel
exporter is parked. Every other disposition in that file is folded into this
revision.
