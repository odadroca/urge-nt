# Orchestrator Comparison & Import Implications

> Status: comparative analysis + actionable scope implications. Not a plan,
> but contains concrete decisions that shape the plugin-import MVP.
> Companion to `langfuse-integration-plan.md`,
> `claude-plugin-import-notes.md`, and `contracts-layer-notes.md`.
> The four together sketch URGE's positioning and the next phase of work.

## Why this note exists

URGE-with-all-discussed-features (registry + runtime + observability + content
imports + policy) overlaps in user-perceptual space with established agentic
orchestrators — CrewAI, LangGraph, AutoGen. A user wandering in might
reasonably ask "is this another orchestrator?" The honest answer is no, but
the comparison surfaces three things worth capturing:

1. **A paradigm taxonomy** — useful for thinking, talking, and pitching.
2. **An import-feasibility analysis** — different orchestrators are differently importable into URGE. This directly shapes plugin-import MVP scope.
3. **A positioning insight** — URGE occupies a cell of the design space the others don't, and the gap is structural, not accidental.

This isn't only strategy. The middle item especially has concrete consequences
for which integrations to prioritize first and which to deprioritize.

## Orchestration paradigms — quick taxonomy

Four shapes, four mental models:

| Tool | Orchestration model | Strength | Weakness |
|---|---|---|---|
| **CrewAI** | Role-based "team of specialists." Agents with role/goal/backstory; tasks assigned; sequential or hierarchical (manager-driven). | Clean mental model. Quick to spin up. Good for "team executes a workflow." | Limited expression of complex conditional flow. Manager-driven hierarchy is the only dynamic option. |
| **LangGraph** | Graph of nodes + edges with conditional routing. Central state object mutates as nodes execute. First-class loops, cycles, checkpointing, time-travel. | Most expressive of the three. Handles arbitrarily complex flow. | Verbose for simple cases. Tight LangChain coupling. Orchestration *is* the Python — no declarative artifact to inspect or share. |
| **AutoGen** | Conversational. Agents talk in a group chat; manager picks the next speaker; flow emerges dynamically. Strong code-execution agents. | Dynamic and emergent. Good for research-y / exploratory workflows. | Hard to predict cost/latency. Hard to test deterministically. |
| **URGE Pipeline** | Static parallel channels + single synthesis pass. N agents run concurrently; one synthesis agent merges. | Predictable cost and latency. Trivially observable. Declaratively storable in a database row. | One shape only. No conditional routing, loops, dynamic dispatch, recursive spawning, or agent-decided next-step. |

## Where URGE is intentionally weaker on orchestration

URGE's Pipeline is **deliberately narrow**. Some flows the other tools express
trivially that URGE cannot:

- **Conditional routing** — "if security agent flags an issue, dispatch to remediation; otherwise return." LangGraph: native. URGE: not expressible.
- **Loops / iteration** — "keep refining until threshold met." LangGraph: native. CrewAI: clunky. URGE: not expressible.
- **Dynamic dispatch** — orchestrator decides at runtime which sub-agent to invoke based on state. AutoGen: native. CrewAI hierarchical: native. URGE: not expressible.
- **Stateful workflows** — accumulate context across many steps; branch from any prior state. LangGraph: native. URGE: stateless beyond the synthesis input.
- **Inter-agent dialogue** — agents converse to refine output. AutoGen: native. URGE: parallel channels can't talk to each other.

If a workflow needs any of the above, URGE's Pipeline cannot host it natively.
This is the **orchestration fidelity gap** flagged in the plugin-import notes,
generalized: the gap exists for any rich orchestrator, not just Claude plugins.

## Where URGE is stronger — the surrounding ecosystem

For everything *around* orchestration, URGE-with-all-features outclasses the
three orchestrator libraries:

| Capability | CrewAI | LangGraph | AutoGen | URGE |
|---|---|---|---|---|
| Versioned prompt registry (branches, fragments, includes) | No | No | No | Yes |
| No-code authoring UI for non-developers | No | No | No | Yes (React SPA) |
| Multi-LLM-client execution surface (MCP / API / Custom GPT) | No | No | No | Yes |
| Built-in observability integration (paired with Langfuse) | Manual instrumentation | Manual instrumentation | Manual instrumentation | First-class (per Langfuse plan) |
| Built-in policy/contracts layer (cost ceilings, provider/model allowlists) | No | No | No | First-class (per contracts notes) |
| Cross-orchestrator content portability (import definitions) | No | No | No | Yes (plugin import — pattern extends) |
| Multi-tenant / team scoping | App-level | App-level | App-level | Yes (Teams + visibility + OAuth scopes) |
| Self-hostable, single deploy | Library only | Library only | Library only | Yes (Laravel + SQLite) |
| Result archive with evaluation pipeline | App-level | App-level | App-level | Yes (Result + ResultEvaluation) |

The three orchestration libraries are *libraries*. They assume you bring
registry, UI, observability, policy, multi-tenancy, and storage yourself. URGE
*is* those, with light orchestration mixed in.

## The 2×2 that clarifies positioning

```
                          Orchestration sophistication
                          LOW                    HIGH
                        ┌─────────────────┬─────────────────┐
   Surrounding         HIGH│  ◆ URGE (full)  │   (empty cell)  │
   ecosystem               │                 │                 │
   (registry / policy /    ├─────────────────┼─────────────────┤
   observability /         │  Plain template │  ◆ CrewAI       │
   multi-tenancy)      LOW │  tooling        │  ◆ LangGraph    │
                           │                 │  ◆ AutoGen      │
                        └─────────────────┴─────────────────┘
```

URGE-with-everything-implemented sits in a cell almost no one occupies. The
three orchestrators cluster in the high-sophistication / low-ecosystem cell.
The high/high cell — heavyweight orchestration *and* deep ecosystem in one
product — is **empty**.

That empty cell is a genuinely interesting strategic position to notice. **It
would require URGE to develop richer orchestration primitives**, which the
preceding notes correctly flagged as feature-creep. So URGE *deliberately*
stays in the low-orchestration / high-ecosystem cell, and the empty cell
remains empty unless future evidence justifies investment.

## "Who wins for which user" — honest read

- *"Complex multi-agent system, iterative reasoning, dynamic dispatch, persistent state."* → **LangGraph.** Don't use URGE for this.
- *"Clean role-based team executing sequential or hierarchical workflow."* → **CrewAI.** URGE can approximate sequential workflows; loses fidelity for hierarchical.
- *"Agents conversing to refine an answer, code execution, research-y patterns."* → **AutoGen.** URGE has nothing equivalent.
- *"Versioned prompt registry, multi-tenant access, observability, policy enforcement, simple multi-agent synthesis, AND the ability to import and govern orchestrations from other tools."* → **URGE.** Not a competitor to the above — a peer in a different role.
- *"Enterprise team needs to share, version, and govern prompt-centric LLM work, and run it from chat UIs without burning API quotas."* → **URGE.** None of the three competes here.

## Import feasibility — concrete feature scope implication

A natural follow-up: can URGE import orchestrations from CrewAI / LangGraph /
AutoGen the way it imports Claude plugins? The answer differs sharply per
tool and **directly shapes which integrations to prioritize**.

| Tool | Import feasibility | Best URGE role |
|---|---|---|
| **Claude plugins** | High. Plugin manifests + skill/agent markdown are declarative. Import → Prompts (skills, commands, non-orchestrating agents) + Pipeline (orchestration-shaped agent groups) + wrapping Collection. | Authoring + observability + policy + execution. Full surface. |
| **CrewAI** | Medium-high. Newer CrewAI versions support YAML-defined agents and tasks. Import maps: agents → Prompts; tasks → Prompts; sequential process → Pipeline; hierarchical → loses fidelity. Cleanest of the three to import. | Authoring + observability + policy. Pipeline execution only for sequential crews. |
| **LangGraph** | Low. The graph *is* Python code. Importing means parsing arbitrary AST or requiring a declarative spec users don't normally produce. Agent system prompts importable as standalone Prompts; orchestration graph not. | Registry + observability companion. URGE stores prompts; LangGraph keeps the graph in code. |
| **AutoGen** | Low. Group chat semantics live in code. Individual agent system prompts importable as Prompts; orchestration not. | Registry + observability companion. Same shape as LangGraph. |

**Implication for plugin-import MVP scope**: the import feature targets
**declaratively-specified orchestrators first** (Claude plugins as primary;
CrewAI YAML as fast-follow when integration #2 has a real user). For
code-first orchestrators (LangGraph, AutoGen), URGE positions as a *registry
and observability companion*, not an import target. Don't promise what can't
be delivered — silent fidelity loss on a LangGraph import would be worse
than not offering it.

This sharpens the existing plugin-import notes' MVP scope: the loose-schema
parser is correct, but the parser surface is **YAML/JSON manifest +
markdown-with-frontmatter**, not Python AST. That's a discipline to hold.

## Implications for messaging / positioning

If URGE describes itself, the right phrasing is:

> "URGE is a versioned prompt registry, runtime, and governance plane for
> prompt-centric LLM systems. It ships with light multi-agent orchestration
> (parallel channels + synthesis), built-in observability via Langfuse, and a
> policy layer for cost and provider control. It complements heavyweight
> orchestrators like LangGraph, CrewAI, and AutoGen — importing their
> declarative content where possible, and serving as registry + observability
> + governance companion where not."

What URGE should *not* claim:
- Not "an alternative to LangGraph/CrewAI/AutoGen"
- Not "agentic orchestration framework"
- Not "build complex AI workflows"

Those framings invite head-to-head comparison on a dimension URGE isn't
optimizing for, and lose. The current framing wins because it sells what URGE
actually is, not what it isn't.

## Strategic upshot

URGE-with-everything-implemented is **the governance plane and prompt registry
for prompt-centric LLM systems — including those orchestrated by other tools**.
This is a stronger market position than trying to out-orchestrate the three
orchestrators, because the orchestration field is crowded and the
surrounding-ecosystem field is nearly empty.

The instinct to *not* expand URGE's orchestration primitives is correct.
Pipeline-as-static-parallel-plus-synthesis is the right amount of orchestration
for URGE's product story. Anything more sophisticated should arrive *imported
from* other tools, not invented inside URGE — and even imported, only when
the source is declaratively specified.

## When to revisit

- **If a user shows up wanting CrewAI YAML import** — extract the shared shape between Claude-plugin parser and CrewAI parser into a generic "declarative-orchestration importer" interface. Don't pre-build it; two real cases beat one imagined symmetry.
- **If a user repeatedly asks for conditional routing in URGE Pipelines** — that's the signal that the high/high cell is being asked for. Resist for as long as it's one user; investigate when it's three.
- **If LangGraph users start using URGE as registry + observability** — that's confirmation of the "companion, not import target" positioning. Document the integration pattern (probably a thin SDK that wraps LangGraph nodes to fetch prompts from URGE and emit traces to Langfuse via URGE).
- **If a fourth orchestrator emerges and dominates** — re-run this comparison. The taxonomy probably still holds; the import-feasibility verdict will need updating per-tool.
