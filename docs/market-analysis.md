# URGE — Market Analysis & Positioning

## Context

The author of URGE (a self-hosted prompt registry with version control + MCP) asked for an outside product/PM read on three things: (i) capability inventory, (ii) competitive landscape, (iii) market positioning — and specifically wants the hypothesis "URGE is more valuable to **mobile users** than to **enterprise users**" tested. Plus a shipping/monetisation read, a self-critique, and reusable research prompts to chase the open questions.

This file is the deliverable. No code changes proposed.

---

## 1. Capability inventory (what URGE actually is, as of 2026-05-14)

**One-line pitch (README.md:3):** *"A prompt registry with version control, built for LLMs and the humans who wrangle them."*

**Core primitives**
- **Prompt registry** with immutable `PromptVersion`s, auto-numbered globally and per-branch, soft-deletable prompts, type = `prompt` or `fragment`.
- **Git-like branching** (`PromptBranch`, `main#3 (v7)` notation, no merge yet).
- **Includes & variables** — `{{var}}` substitution + recursive `{{>slug}}` fragment includes with circular-ref detection (TemplateEngine).
- **Results archive** — every run (manual, API, MCP, scheduled) is stored, ratable, starrable, downloadable as `.md`.
- **LLM-powered evaluation** — versioned scoring across 6 configurable dimensions, composite score, auto-evaluate.
- **Multi-channel pipelines** — fan a prompt across providers/models; analytical channels can replay over historical Results.
- **Collections** (DAG-nestable) for curation, with public share tokens (`/share/{token}`).
- **Teams** (flat, role-based: owner|member) for sharing private prompts.

**Surfaces** (this is the unusual part)
| Surface | Audience | State |
|---|---|---|
| React 19 SPA at `/app/*` | Humans on desktop/tablet/phone web | Live, responsive bottom-tab nav (`Layout.jsx:7`, `Sidebar.jsx:96-115`) — no PWA, no native shell |
| REST API `/api/v1/*` | Any HTTP client / CustomGPT actions | Live, OpenAPI 3.1 doc at `/docs` (Scalar, no auth) |
| MCP server (Streamable HTTP) | Remote MCP clients — Claude.ai, Claude Desktop, Mistral Le Chat | Live, protocol 2025-06-18, ~29 documented tools (37 declared in code) |
| MCP server (stdio) | Local MCP clients — Claude Code | Live, `php artisan urge:mcp-server` |
| Claude Skill / CustomGPT | Inside Claude Projects / GPTs | Live |

**Auth** — Sanctum (SPA) → OAuth 2.1 with PKCE + Dynamic Client Registration (RFC 7591) + GitHub IdP → API keys. Refresh tokens with rotation. Scopes `mcp:read|write|admin`. This is uncommonly thorough for a small project.

**What's missing (matters for positioning)**
- ❌ No billing / plans / quotas / metering — zero hits for stripe/paddle/lemonsqueezy/subscription/quota anywhere in code.
- ❌ No native mobile app, no PWA manifest, no service worker, no offline.
- ❌ No audit log, no SCIM, no SSO beyond GitHub.
- ❌ No A2A / agent-to-agent protocol hooks (only MCP).
- ❌ No hosted demo URL or marketing landing page in the repo.
- ❌ No observability dashboard for prompt cost/latency over time (Results exist but there's no analytics surface like Langfuse / Helicone).

---

## 2. Competitive landscape

URGE sits in a category I'd call **"prompt ops"** — version control + storage + execution + eval for prompts. The category has fragmented into four sub-segments. URGE straddles two of them.

### Segment A — Prompt management / version control (URGE's home turf)
| Competitor | Model | Where URGE wins | Where they win |
|---|---|---|---|
| **PromptLayer** | SaaS | Self-host, MCP-native, OAuth depth | Larger user base, integrations |
| **Humanloop** | SaaS, enterprise | Self-host, open-er feeling | Eval depth, SOC2, sales motion |
| **Langfuse** | OSS + SaaS | Simpler model | LLM observability (traces, cost), much bigger community |
| **Helicone** | OSS + SaaS | Prompt-centric (not log-centric) | Proxy-based observability, cost analytics |
| **Vellum** | SaaS | Self-host, MCP-native | Workflow builder, eval suite |
| **Agenta** | OSS + SaaS | MCP-native | Eval-first, A/B testing |
| **PromptHub** | SaaS | Pipelines, MCP | Native Git integration, team focus |
| **Pezzo** | OSS | Simpler, more focused | Observability, broader instrumentation |
| **LangSmith** | SaaS (LangChain) | Framework-agnostic | LangChain ecosystem lock-in |
| **Braintrust** | SaaS | Self-host | Eval-first, fast-growing |

### Segment B — MCP-native registries / marketplaces (URGE's emerging turf)
- **Smithery.ai**, **mcp.so**, **PulseMCP** — these list/distribute MCP **servers**, not prompts. URGE is an MCP **server** that distributes prompts. Different layer.
- **Anthropic's Skills** — closest conceptual cousin to URGE's "prompt as resource". Skills live inside Claude; URGE prompts can be consumed by *any* MCP client. URGE is the cross-vendor neutral registry.

### Segment C — Prompt marketplaces (consumer / mobile-adjacent)
| Competitor | Model | Notes |
|---|---|---|
| **PromptBase** | Marketplace | Sells prompts as products, ~$2-10 each |
| **FlowGPT** | Free + premium | Social/marketplace, mobile app exists |
| **AIPRM** | Browser extension + SaaS | ChatGPT-only, big user base |
| **PromptHero** | Free | Mostly image-gen prompts |
| **Promptly / Snack Prompt** | Free / freemium | Community + curation |

These are **discovery** plays. URGE is **infrastructure**. Different value prop.

### Segment D — Mobile-first AI chat with prompt libraries
- **Pal Chat (iOS)**, **Poe (Quora)**, **TypingMind**, **ChatBox**, **Msty** — these bundle a chat UI + saved prompts/personas. They are URGE-adjacent only in that they let mobile users save and reuse prompts. None of them version, share via team, or expose a registry API.

**TL;DR positioning gap:** URGE's distinctive combination is **(self-hosted + MCP-native + multi-surface + OAuth-grade auth)**. No single competitor hits all four. The closest is probably **Langfuse** (OSS + self-host + getting MCP), but Langfuse is observability-first, URGE is registry-first.

---

## 3. Positioning recommendation

The strongest positioning is **not** "another prompt manager" and **not** "mobile prompt vault". It is:

> **"The prompt memory layer for the agentic stack — self-hosted, MCP-native, multi-tenant by design."**

Why this works:
- It rides the 2025-2026 agentic wave (MCP, A2A, multi-agent) where prompts are the **state** that agents share.
- It picks the part of the stack nobody owns yet — a *neutral* registry below the agent runtime.
- "Self-hosted" is a real differentiator for any regulated buyer (legal, healthcare, gov, EU data residency).
- It quietly de-emphasises the crowded "prompt mgmt SaaS" framing where URGE is late and small.

Three positioning **axes** to lead with:
1. **For developers building agents** — "Cursor / Claude Code / your custom agent fetches prompts from URGE via MCP, runs them, stores results. Same registry, every agent."
2. **For platform teams in mid-market companies** — "Your prompts are IP. Keep them in your VPC. Give every team a namespace. Audit later."
3. **For LLM-vendor-agnostic shops** — "Don't lock your prompts into OpenAI's Playground / Anthropic's Workbench. URGE renders to any provider."

---

## 4. Testing the author's thesis: mobile-users > enterprise-users

The author's gut says URGE is more valuable for mobile users than for enterprise. **My honest read: the gut is half-right but inverted on shipping reality.**

### Where the mobile thesis has merit
- The mobile user **does** suffer from prompt-loss across apps (ChatGPT app, Claude app, Perplexity, Pi). A universal prompt library reachable from a phone is a real unmet need.
- The SPA already has a phone-friendly bottom-tab layout. The screens (Browse, Workspace, Settings) work on small viewports.
- "I'm on my phone, I want to grab my résumé-reviewer prompt and paste it into Claude iOS" is a credible JTBD that URGE can serve **today** via the web app.
- Mobile is where the next 100M LLM users live. Anthropic / OpenAI mobile apps are exploding.

### Where the mobile thesis falls apart
- **Self-hosted is incompatible with mobile users.** A consumer mobile user will not run a Laravel server. Mobile demands a hosted SaaS. URGE has zero billing/auth-for-strangers / onboarding-for-strangers code.
- **No native app.** Mobile users live in the App Store / Play Store, not bookmarks. The web SPA, even responsive, has 4–10× lower retention than a native app for utility tools.
- **MCP is irrelevant on mobile** today — Claude/ChatGPT mobile apps don't speak MCP. The killer feature is dead weight on iOS until Apple/Google ship MCP-aware OS integrations (Apple Intelligence may; not bet-worthy).
- **Mobile prompt-paste is a 3-tap friction problem**, not a registry problem. The winning UX is a **share-sheet extension** ("send to Claude") or a **keyboard extension** — neither of which URGE has.
- **Enterprise is, in fact, where every feature URGE built lines up:** OAuth 2.1, namespaces, teams, immutable versions, audit-shaped data, self-host. The author built an enterprise product and is suspicious of his own work because the demo looks like a consumer app. Classic founder pattern.

### My verdict
- **The enterprise / dev-platform reading is stronger today** with the *current* codebase. Ship it to platform engineers and AI-platform teams in mid-market companies first.
- **The mobile/prosumer reading is the right *second* product** — but it needs (a) hosted SaaS, (b) iOS/Android app or at least PWA + share extension, (c) a free tier. That's 3-6 months of work URGE hasn't done.
- **Best of both worlds path:** ship the prosumer SaaS as a *single-user* slice of the same codebase (free tier on `urge.app` or similar) — it doubles as developer-relations / top-of-funnel for the enterprise self-host motion. GitLab's playbook.

---

## 5. How to ship it (meaning, usability, surfaces)

### Three honest shipping shapes
1. **Self-hosted open-core (today's shape, productised)**
   - Free OSS core (Apache 2.0 or BSL).
   - Paid "Team / Enterprise" add-ons: SSO/SCIM, audit log, RBAC beyond teams, SLA, support.
   - Deploy targets: Docker image, Helm chart, one-click installers for Coolify / Railway / Render.
   - Sales motion: founder-led, GitHub-stars-driven, Discord for support.

2. **Managed SaaS (urge.cloud) — minimum viable**
   - Same codebase, multi-tenant via the existing namespaces.
   - Stripe integration (doesn't exist; need ~2 weeks of work).
   - Free tier (50 prompts, 1 team, no SSO), Pro $9/seat/mo, Team $19/seat/mo, Enterprise (call us).
   - This is the path to mobile-user revenue.

3. **Prosumer mobile (Phase 2)**
   - iOS + Android app — thin clients over the API.
   - Killer feature: **iOS share-sheet** ("share to URGE"), **Android share intent**, **iOS keyboard extension** to paste prompts into any AI app.
   - Subscription: $4.99/mo Personal, $9.99/mo Pro (sync + teams).
   - Optional: prompt marketplace, revenue share with prompt authors.

### Usability fixes needed in any shipping shape
- **Onboarding for strangers.** Today's signup assumes a self-host operator. Mobile/SaaS needs Google/Apple sign-in, magic-link, instant first-prompt creation.
- **Mobile workspace.** The 3-panel workspace editor is desktop-shaped. Phones need a single-pane "open prompt → edit → save" loop.
- **A "send to" surface.** Whether share-sheet, keyboard, or a bookmarklet — the bridge from URGE to "paste into Claude" is the missing link.
- **Public prompt gallery.** Discovery is currently zero. A read-only `urge.app/explore` of shared prompts would seed both SEO and the marketplace narrative.

---

## 6. How to sell it (monetisation models, ranked)

| Rank | Model | Target buyer | ARPU/yr | Effort to build | Why this rank |
|---|---|---|---|---|---|
| 1 | **Per-seat SaaS** (Pro/Team/Enterprise) | Dev teams, AI-platform teams | $100–$500 | Medium (Stripe + multi-tenant hardening) | Recurring, defensible, fits the asset |
| 2 | **Self-host enterprise license** (Business Source) | Regulated mid-market & gov | $20k–$100k | Low (existing code + sales pages) | Highest contract value, fewer customers needed |
| 3 | **Prosumer mobile subscription** | Heavy LLM users (ChatGPT/Claude power-users) | $60 | High (native apps) | Tests author's mobile thesis with revenue |
| 4 | **Marketplace take-rate** (sell community prompts) | Prompt authors → consumers | Variable | Highest | PromptBase exists; rough fight |
| 5 | **Usage-based API metering** | Programmatic users | Variable | Medium | Hard to price; can layer on top of (1) |

**Recommended monetisation sequence:**
1. **Now:** Quietly take 3-5 design partners on a paid self-host license (Rank 2). Use them to write the case-studies.
2. **+3 months:** Ship `urge.cloud` Rank 1 with a free tier. Free tier doubles as the mobile prosumer entry.
3. **+9 months:** Ship iOS app (Rank 3) only if the free-tier mobile-web cohort shows >15% activation. Otherwise kill the mobile branch.

---

## 7. Self-critique

### Pros of this analysis
- **Grounded in the actual code** (verified surfaces, auth, missing billing, no PWA, ~29-37 MCP tools).
- **Explicitly tests the author's thesis** rather than rubber-stamping it.
- **Names concrete competitors** in the segments URGE actually overlaps with.
- **Gives a shipping sequence with a kill switch** (mobile only if free-tier activation > 15%).
- **Honest about the open-core / enterprise / mobile tension** rather than picking one and ignoring the others.

### Cons / weaknesses of this analysis
1. **No user evidence.** I have not talked to a single URGE user. All JTBDs are inferred. The "prompt loss on mobile" pain is a hypothesis, not a finding.
2. **No competitor pricing depth.** I named PromptLayer/Humanloop/Langfuse/etc. but didn't pull actual 2026 pricing tiers, customer counts, or last funding round. Positioning needs that.
3. **No traffic/SEO assessment.** "Self-hosted MCP prompt registry" — is anyone Googling this? Volume unknown.
4. **MCP-as-moat may be temporary.** If OpenAI/Google adopt MCP and host registries themselves, the neutral-registry positioning erodes fast.
5. **Mobile retention claim is hand-waved** ("4–10× lower for utility web apps"). Source not cited; might be wrong for this category.
6. **No regulatory/data-residency check.** I claimed self-host is a real differentiator for regulated buyers, but I haven't checked whether URGE actually meets typical SOC2/ISO27001/EU-AI-Act requirements out of the box. Probably doesn't.
7. **The "mid-market platform team" buyer persona is asserted, not validated.** That's a known crowded buying centre in 2026 (every dev-tool startup chases it).
8. **No CAC/LTV math.** Rankings in §6 are gut-feel, not unit-economics-driven.
9. **Anthropic Skills risk is under-weighted.** If Skills become the de facto cross-vendor format (they won't be, but if), URGE's prompt-as-MCP-resource framing competes directly with a first-party Anthropic primitive.
10. **No assessment of the founder's actual constraints** — time, capital, distribution channels. A solo / 2-person team should not chase 3 monetisation models in parallel.

---

## 8. Ready-to-use prompts to investigate the self-critique points

Each prompt is self-contained — paste into any capable LLM (GPT-5/Claude-Opus-4.7/Gemini-Ultra-3 etc.) with web access, or run via URGE's own pipeline against a web-enabled provider.

### Prompt 1 — User evidence (addresses con #1)
```
You are a user-research strategist. I have built URGE, a self-hosted, MCP-native prompt registry with version control. I have no user interviews yet. Design a 5-day "minimum viable evidence" research sprint that will tell me whether (a) AI-platform engineers at 50-500-person companies actually want a neutral, self-hosted prompt registry, AND (b) heavy mobile LLM users (>10 ChatGPT/Claude messages/day on phone) lose prompts across apps frequently enough to pay $5/mo. For each day, give: the activity, the recruiting channel, the exact screener question, and the 6 interview questions. End with a kill/continue decision rule for each hypothesis.
```

### Prompt 2 — Competitor pricing & traction pull (addresses con #2)
```
Pull the current (2026) pricing pages, free-tier limits, advertised customer counts, and last public funding round for each of: PromptLayer, Humanloop, Langfuse, Helicone, Vellum, Agenta, PromptHub, Pezzo, LangSmith, Braintrust. Output as a markdown table with columns: Vendor | Lowest paid tier ($/seat/mo) | Free tier cap | Self-host offered? | Notable customers | Last raise (amount + date) | Notes on MCP support. Cite sources for each row. Where data is missing, say so.
```

### Prompt 3 — Search demand sizing (addresses con #3)
```
Estimate global monthly search volume in 2026 for these query clusters: "prompt management", "prompt registry", "prompt version control", "self-hosted prompt", "MCP prompt server", "prompt library mobile", "save prompts iPhone". For each cluster, give: (a) estimated monthly volume, (b) keyword difficulty 0-100, (c) intent (commercial / informational), (d) top 3 ranking domains today. Use whatever public sources you can — Google Trends, Ahrefs/Semrush samples, autocomplete patterns. Be explicit about confidence level for each row.
```

### Prompt 4 — MCP defensibility stress test (addresses con #4)
```
Act as a contrarian product strategist. URGE's central wager is that being "MCP-native and vendor-neutral" is a durable position. Argue the opposite for 800 words: lay out the strongest case that within 18 months MCP will (a) be subsumed by first-party registries from OpenAI/Anthropic/Google, (b) be replaced by a different open standard (A2A? something newer?), or (c) become commoditised infrastructure with no value capture for neutral players. Then propose three concrete things URGE could build NOW to remain defensible even if your contrarian case plays out.
```

### Prompt 5 — Mobile retention reality check (addresses con #5)
```
I claimed that responsive web utility apps retain 4-10× worse than native iOS/Android apps over a 30-day window. Find evidence for or against this claim specifically for AI-adjacent utility apps released 2023-2026. Look for: published retention benchmarks (App Annie, Sensor Tower, Adjust, Branch reports), case studies of consumer AI tools that shipped both web and native (Perplexity, Poe, Pal, Replika, Character.AI), and PWA-vs-native comparisons. Output: a numbered evidence list, an honest summary verdict (claim holds / overstated / understated), and the implications for a prompt-library product weighing PWA vs native iOS.
```

### Prompt 6 — Compliance gap audit (addresses con #6)
```
You are a SOC2 / ISO27001 / EU AI Act readiness auditor. Given the URGE feature inventory below, list every gap that would block enterprise adoption in regulated industries (banking, healthcare, gov, EU). Format: gap | severity (P0/P1/P2) | what's required | rough engineering effort (S/M/L).

Inventory:
- Laravel 12 / PHP 8.3, SQLite (configurable to Postgres), self-hosted
- Auth: Sanctum sessions, OAuth 2.1 (PKCE, refresh tokens, DCR), API keys
- Identity: local users + GitHub OAuth as external IdP
- No audit log of admin actions
- No SCIM
- No data-residency controls beyond "wherever the operator deploys"
- No field-level encryption beyond Laravel's default encrypted attribute
- No DPA / SLA documents
- Soft deletes only, no retention policy controls
- No PII scanner / redaction
- Logging: standard Laravel logs to disk, no SIEM integration
```

### Prompt 7 — Buyer persona validation (addresses con #7)
```
Act as a senior buyer at a 200-person SaaS company. Specifically, act as the head of an "AI Platform" team (3-6 engineers building internal LLM tooling). I will pitch you URGE as a self-hosted, MCP-native prompt registry. Ask me the 10 hardest questions you would actually ask before buying, in priority order. After I (the founder) answer each one, score the answer 1-5 and tell me whether you would (a) move to a pilot, (b) ask for more, (c) pass. Be willing to pass. Begin by asking your first question.
```

### Prompt 8 — Unit-economics model (addresses con #8)
```
Build a 36-month bottoms-up financial model for URGE under two monetisation paths:
A) Self-host enterprise license at $40k/yr, founder-led sales, 12-week sales cycle.
B) Per-seat SaaS at $15/seat/mo average, free tier funnel, product-led growth.

For each, output a markdown table with columns: Month | New logos | Total logos | Avg seats per logo (B only) | MRR | ARR | CAC assumption | LTV/CAC | Cash burn assumption. Use reasonable 2026 SaaS benchmarks (Bessemer / OpenView style). Assume a 2-person founding team, $50k/mo burn floor. End with a one-paragraph recommendation: which path crosses break-even first, and what the dominant risk is for each.
```

### Prompt 9 — Anthropic Skills threat model (addresses con #9)
```
Anthropic shipped "Skills" as a way to package prompts + tool access inside Claude. Assess the threat this poses to URGE (a cross-vendor, MCP-native prompt registry). Cover: (1) feature overlap (where does Skills already do what URGE does), (2) feature gaps (where URGE clearly wins or loses), (3) two scenarios — Skills stays Claude-only vs. Skills opens to other vendors via some adapter — and what URGE's positioning should be in each, (4) one decisive product bet URGE should make in the next 90 days to remain distinct regardless of which scenario plays out. Be concrete and avoid generic strategy-speak.
```

### Prompt 10 — Solo-founder constraint planner (addresses con #10)
```
I am a solo (or 2-person) team building URGE. I have ~20 hours/week and ~$30k of runway before I need either revenue or a job. Given the three monetisation paths (enterprise self-host license, managed SaaS, mobile prosumer), tell me exactly which ONE to pursue for the next 90 days and why. Then give me a week-by-week 90-day plan: each week has a single goal, a single deliverable, and a single metric I will not move past until it's hit. Be ruthless — if I'm clearly off-track by week 4, tell me what the kill criterion is.
```

---

## Verification (how to know this analysis was useful)

- ✅ Did the author find at least 2 of the 10 self-critique cons sharp enough to act on?
- ✅ Did running prompts 2, 3, 5 produce concrete data the author didn't have before?
- ✅ Did prompt 1 (user evidence sprint) get scheduled within 2 weeks of reading this?
- ✅ Did the author end the read with a clearer one-sentence answer to "who is URGE for?" than they started with?

If 3 of 4 ✅ → analysis was worth the read. If 0-1 → throw it out, re-do with real user data.
