# URGE v3 Design — Independent Audit (consolidated)

Date: 2026-11-22
Subject: `2026-11-22-urge-v3-from-scratch-design.md` as of commit d41e793
Method: four independent reviewers, run in parallel, each with the document and
the relevant source code, none given the author's conclusions. Lenses: security
and authorization; architecture and scope; owner workflow and MCP ergonomics;
delivery and operations. Findings below are deduplicated and ranked by the
author. "Disposition" states what the author intends; items marked OWNER need
the owner's decision.

Overall verdicts: security "not approvable as written (one Critical)";
architecture "approve with changes"; workflow "surface needs six additions";
delivery "feasible if M3 is split and hosting is always-on".

---

## A. Blockers (change before M0)

**A1. Authorization is promised, not structural.** `authorize(actor, action,
resource)` on an already-loaded resource is the v2 shape that produced four
Critical IDORs. Stores have no actor parameter; id-addressed inputs abound
(`get_run`, `complete_channel`, `result:<id>` targets, `urge://runs/{id}`).
Disposition: stores expose only actor-scoped reads (`get(actor, id)`,
`list(actor, filter)`) implemented as one SQL visibility predicate per entity;
unscoped reads exist only for a `system` actor used by executor resume; uniform
404 for invisible-or-missing; a test fails the build if any service or store
method lacks an `Actor`.

**A2. The carried kernel is JSON-only.** Calane's `ChannelExecutor` parses and
validates unconditionally; prose channels would end `invalid_json` and every
research run `partial`; `{{channel_results}}` and the bundle's `final.md` render
`parsedOutput`, null for prose. Disposition: validation runs only when
`output_schema` is set; otherwise `parsed_output = null`, status `ok`,
`{{channel_results}}` renders `raw`. Validator, renderer, exporter move from
"carried" to "adapted". Realistic as-is carry from Calane is 800–1,000 lines
(topological plan, retry/repair loop, Ajv wrapper, hashing, specs), not 2,500.

**A3. Run state machine is a sketch.** `interrupted` missing from the enum;
`depends_on` × `pending_client` undefined; `complete_channel` idempotency
undefined; `save_pipeline` replace during an `awaiting_client` run undefined;
dispatch ownership when `complete_channel`, `run(resume)` and a poll overlap
undefined. Disposition: a transition table (ChannelResult × events: dispatch,
complete, finalize, restart, resume; Run derived); dependencies resolve within
one target, a level releases when all prior-level items for that target are
terminal; a Run snapshots its resolved pipeline spec at start; pending items
carry a stable `item_id` and `complete_channel` takes it; a second completion
on a terminal item is a no-op returning the existing result.

**A4. Visibility rules have holes `authorize()` cannot be written without.**
Synthesis Results with null `prompt_version_id` listed under every targeted
prompt leak mixed-target verdicts; B's results on A's shared prompt vanish for
B when A flips it private; anyone who can see a prompt may `complete_channel`
or `finalize_run` someone else's run. Disposition: (1) a Result with `run_id`
takes the Run's visibility; (2) a Run is visible to its requester, or to anyone
who can see the pipeline and every target; a synthesis over mixed-visibility
targets is requester-only; (3) `complete_channel`, `finalize_run`, `run(resume)`
require actor = requester or admin; (4) a recorder always sees what they
recorded, regardless of later visibility flips. OWNER: whether `is_admin` may
read private records (author recommends no).

## B. High (change before the affected milestone)

**B1. Identity by email reopens v2 AUTH-03.** Disposition: Person identity is
`(iss, sub)` only; email is at most a one-time admin bootstrap hint.

**B2. §7.6's IdP claim is wrong for cheap providers.** Auth0 and Entra issue
JWT access tokens only when an API/audience is registered; ChatGPT reportedly
omits the RFC 8707 `resource` parameter; redirect URIs are not three and not
known up front (ChatGPT per-connector callback, Codex localhost port).
Disposition: deploy guide gains an explicit "register API + default audience"
step; PRM advertises `scopes_supported`; verifier accepts RS256 and ES256 and
has an introspection fallback; M3's first check is a raw-token `curl`; redirect
URIs registered after each connector draft; Codex callback port fixed.

**B3. Carried Calane auth code has gaps the design denies.** Fail-open when
unconfigured; unauthenticated `initialize`/`tools/list`; token accepted from
`params._meta.token`; no `exp` required; JWKS refetch unbounded; sessions never
evicted. Disposition: all six listed as required changes to carried code; HTTP
transport refuses to start without auth config; auth at the HTTP layer before
JSON-RPC dispatch; header-only bearer.

**B4. Stateful Streamable HTTP sessions die on every restart; several clients
do not re-initialize on 404.** Disposition: run stateless (no `Mcp-Session-Id`
state); `requested_via` derived from the token's `client_id`/`azp` or the API
key name, never from `initialize` clientInfo.

**B5. Ref grammar.** `#n` means global in one place and branch-local in another;
`#` is a URI fragment and is stripped by HTTP clients. Disposition: one grammar
everywhere, `slug[@branch][~N]`, with `~N` always the global version number;
branch-local numbers are display-only.

**B6. `input_source: result_history` duplicates target refs** and executes once
per target against the same history. Disposition: dropped; history becomes a
target selector `results:<ref>?since&limit&run_source&kind` that resolves to
one target; visibility enforced in the resolver. M6 becomes a resolver, not a
parallel execution path.

**B7. Fragment variable defaults reverse v2's TPL-05 hardening.** Disposition:
a fragment's defaults apply only to variables the fragment itself introduces.

**B8. Milestone order.** Disposition: M1 owns the persisted state machine and
`interrupted`; M3 splits into M3a (HTTP transport, API keys, Docker, volume,
always-on, `/health`, backup, per-run export, minimal REST `POST /results`;
check: Claude Code over HTTP, restart survives, backup restores), M3b (IdP +
Claude.ai + second person), M3c (ChatGPT), M3d (Codex), each declared done
separately; M0 includes the Person/allowlist CLI.

**B9. Hosting must be always-on** or background judge calls started after the
client disconnects are routinely `interrupted` (Render free spins down and has
no disk; Fly `min_machines_running = 0` stops after idle). OWNER: accept a
paid always-on tier (author recommends yes; the alternative is server-side
execution that cannot be trusted).

**B10. `wait_ms` vs proxy timeouts.** Fly's 60 s idle proxy timeout is fixed;
clients report 60 s drops. Disposition: `wait_ms` on `get_run` too (the tool
actually polled), cap 45 000, SSE progress notifications during the wait.

**B11. WAL-mode backup is not a file copy.** Disposition: `urge backup` using
`VACUUM INTO` to `/data/backups`; admin-key `GET /export/backup` so a home cron
pulls it (keeps the no-outbound rule); restore procedure removes stale
`-wal`/`-shm`; the encryption key lives in env, never on the volume; backups
are plaintext and the guide says so.

## C. Medium

**C1. Tool surface** (workflow reviewer; author agrees with all):
`run` → `run_pipeline` (collides with shell "run" tools); drop `render?` from
`get_prompt` (near-duplicate of `render_prompt`); add `update_prompt` (metadata
and visibility without a new version) and `update_result` (rating, star, notes:
"star the winner" is the conversational end of judging); `run_pipeline(resume,
retry_failed)` re-runs only failed or interrupted items; pending items carry
`item_id` and an `intended_executor` hint so Claude does not run the ChatGPT
slot; `include_rendered=false` option and per-item fetch to control payload;
`render_prompt` returns `resolved_ref` and `rendered_hash`; `store_result`
accepts `variables`, `rendered_hash`, `produced_at`, `source_url`;
`get_results` gains `kind`; `run:<id>` targets exclude `synthesis` by default;
`list_runs` gains `target` and `run_source`; `list_pipelines` gains `scope`;
`save_pipeline` takes `expected_hash`; `executed_by` defaults to `client` and
`recorded_via` is server-derived. Count becomes 16; hard cap raised to 20
(OpenAI's limit is ~30).

**C2. Months-later recoverability gaps.** Branch has no `description` (the v2
"branch identity in a free-text note" complaint is half fixed); no
`skipped_reason`/`finalized_by`; `score` Results lack `scored_result_id`;
share/unshare is not an audit row; standalone Results lack variables and
rendered hash (regression vs v2 `rendered_content`/`variables_used`). Disposition:
all added.

**C3. Scorers cannot see the question.** `result:<id>` targets expose only the
answer. Disposition: `{{target.prompt}}` (the rendered prompt that produced the
Result, when known) alongside `{{input}}`.

**C4. Injection paths.** Variable values and `{{input}}`/`{{channel_results}}`
must be inert (single-pass substitution); deleted slugs are tombstoned, never
reusable; includes resolve to prompt id at save time. Disposition: all three.

**C5. Theme C of the v2 audit dropped silently.** No URL validation on
`Provider.endpoint`, stored schema refs, or the OTel endpoint; Calane returns
raw `err.message`. Disposition: scheme/host/IP allowlist at save and dispatch;
Ajv with remote `$ref` disabled; central error redaction with body-size cap.

**C6. Viewer cookie.** Disposition: `SameSite=Strict`, cookie honoured only on
viewer routes, custom header for writes, CSP, sanitized Markdown rendering.

**C7. DoS and spend.** JWT callers unthrottled; no cap on channel×target; one
person spends the other's provider key. Disposition: per-actor limiter in the
adapter layer; max channel×target per run; body-size limit; per-run cost
ceiling.

**C8. Carried weight with no milestone.** `RunDiffer` (bound to Calane's
`RunResult` shape), bounded recursion, OTel exporter package. Disposition:
drop `RunDiffer` and recursion. OWNER: OTel exporter was carried at the owner's
request; the architecture reviewer recommends keeping only the 64-line sink
interface and parking the exporter (author agrees).

**C9. CLI "thin over REST or in-process"** is two code paths. Disposition: REST
only.

**C10. Dependencies.** `node:sqlite` still experimental on Node 22.
Disposition: `better-sqlite3` on Node 24 LTS, `busy_timeout = 5000`, one Node
pin shared by Dockerfile, `engines`, and the platform config; `urge doctor`
(env, DB opens, JWKS reachable, one-token call per provider) for the six-month
return.

## D. Low

Export filename sanitization; OTel spans never carry prompt or output text;
FTS5 query quoting; `self_reported` on client-supplied provenance; slug
collision on `save_prompt` leaks existence of a private prompt (accepted at
household scale; return "slug taken").

## E. What the reviewers confirmed as right

Resource server only, no AS, no DCR, no refresh rotation. One `Actor` type
with API keys carrying no admin bit. stdio `--as` mandatory with more than one
person. Includes of invisible prompts fail closed; sharing a composite does not
share fragments. Raw output always kept; producer and recorder separated in
provenance. Audience-as-reference visibility. Domain model close to minimal.
Target refs unify the research tree, the scorer, and the distiller. SQLite,
one process, one volume for this scale.

## F. Claims in the design verified inaccurate

- "Parse JSON when a schema exists" is false for Calane as written (A2).
- Run-context variables "carried from Calane into system prompts": Calane has no
  system/user split; carrying it changes the provider adapter interface.
- "Carry ~2.5k lines": realistic as-is carry is 800–1,000 lines (A2).
- "IdP needs no custom scope or API definition": false for Auth0 and Entra (B2).
- "Three redirect URIs": not three and not known up front (B2).
- "Backup is a file copy": unsafe in WAL mode (B11).
