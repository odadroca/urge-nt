# Narrated Observability — Concept Trace

> Status: concept exploration, captured for traceability of the idea — not
> a scoped plan, not (yet) a feature commitment. The value of this note is
> in preserving the framing that emerged, so future-you can decide whether
> to act on it without having to re-derive it.
>
> Companion to `langfuse-integration-plan.md`, `claude-plugin-import-notes.md`,
> `contracts-layer-notes.md`, and `orchestrator-comparison-notes.md`.

## Origin of the question

Earlier notes flagged a real gap: in mode 2 *pure form* (chat-UI-as-registry —
Claude Desktop fetches a prompt from URGE and reasons natively, never calling
back to URGE's runtime), URGE doesn't see the LLM call. Therefore Langfuse
gets nothing about that call. Therefore that call is invisible.

The question was: **what if the prompt itself instructs the LLM to send back
relevant trace metadata in JSON, after the response?** Could URGE then ingest
that self-report and emit some form of trace to Langfuse, partially closing
the gap?

Investigating turned out to be more interesting than the simple yes/no the
question asked for. The investigation produced a framing worth keeping.

## The framing that emerged: narrated vs measured observability

Two structurally different categories of observability data:

- **Measured observability** — ground-truth telemetry the provider records: actual tokens consumed, actual latency in ms, actual provider routing, actual cost in cents. Captured by instrumenting the *call* itself. This is what Langfuse normally gets.
- **Narrated observability** — the LLM telling you, in its response, what it did and why. Captured by *asking* the LLM to report.

These are different categories of data, and the LLM is reliable at one and
unreliable at the other. The framing matters because it cleanly explains
what self-reported metadata can and cannot deliver, without ambiguity.

## What the LLM can reliably self-report

| Data | Reliability | Why |
|---|---|---|
| **Reasoning chain / decisions** ("I chose path A because X") | Good | LLM has direct access to its own reasoning and can narrate it (modulo standard CoT post-hoc-rationalization caveat) |
| **Tool calls made** | Good | LLM knows what it invoked and with what args |
| **Self-described intermediate steps** | Good | Same |
| **Decision path summary** | Good | Useful for orchestration analysis |
| **Output structure / sections produced** | Good | Trivially knowable |
| **Self-assessed confidence** | Mediocre | Well-known LLM calibration issues — useful as noisy signal, not ground truth |
| **Model name / version** | Mediocre | LLMs often misidentify their own version unless system-prompted with it |

## What the LLM *cannot* reliably self-report

| Data | Reliability | Why |
|---|---|---|
| **Actual token counts** | Bad | Notoriously poor self-token-counting; estimates badly |
| **Actual latency / timing** | Bad | No reliable internal clock |
| **Actual cost** | Bad | Derives from bad token counts |
| **Provider routing / region** | Invisible | LLM doesn't see infrastructure metadata |
| **Cache hits, retries, errors below its layer** | Invisible | Happens below LLM awareness |
| **What it "almost said" but suppressed** | Bad | No access to alternative branches considered |

## What this would buy URGE (if implemented)

A real, valuable addition for mode-2-pure-form:

- **Decision-path analysis** — "when this prompt is invoked, what does the LLM typically decide?" Not visible today.
- **Behavioral drift detection** — narrated behavior changes over time as models update. Useful for prompt-quality monitoring.
- **Tool-use audit trail** — what tools did the LLM call, in what order, with what args, and how did it justify each. Useful for debugging multi-tool flows.
- **Reasoning archive** — chain-of-thought captured alongside the result. Useful for evaluation, training data, future distillation.

## What it explicitly does *not* buy URGE

- **Accurate cost / token / latency telemetry** for mode-2-pure-form. The fundamental gap remains.
- Therefore: **cost-ceiling contracts still cannot be enforced post-hoc** for mode-2-pure-form. Self-report can give a *guess* that the contract layer might use as a soft signal, but it's not safe for hard budget enforcement. The conclusion in `contracts-layer-notes.md` (mode-2-pure-form is "advisory only" for contracts) survives this hypothesis intact.
- **Detection of what the LLM didn't narrate.** If the model silently retried, hallucinated and corrected, or did anything it doesn't surface in its narration, you can't see it.

## Implementation sketch (rough — for traceability, not commitment)

The pattern composes nicely with URGE's existing primitives:

1. **Versioned fragment** — `{{>urge-metadata-report}}` — appended to prompts that want self-reported telemetry. Fragment instructs: "After your response, append a JSON block tagged `<urge-metadata>...</urge-metadata>` with keys: `reasoning_summary`, `tools_called`, `decisions`, `self_confidence_1_to_5`, `model_self_reported`."
2. **Parser** in URGE's MCP `store_result` handler extracts the JSON block, separates it from user-facing output, stores on the Result with provenance flag like `metadata_source = 'self_reported'`.
3. **Langfuse emission** sends cleaned output as the generation's `output`, self-reported fields as Langfuse `metadata` keys (tagged `urge:source:self_reported`), and explicitly leaves token/latency/cost fields **null** rather than populating with unreliable estimates.
4. **Analysis layer** treats self-reported scores as one signal among others. URGE's `EvaluationService` can correlate self-reported confidence with later human/LLM-judge scores to learn how miscalibrated self-reports are over time.

The fragment-based approach is clean because it's optional and per-prompt —
only prompts that want narrated observability include it. It's also versioned,
so the metadata schema can evolve without breaking existing imports.

## Hard limitations (structural, not implementation gaps)

1. **Token / cost / latency are structurally unreachable via self-report.** This isn't a "we haven't built it" gap; it's "the LLM cannot produce this data accurately." No amount of prompt engineering fixes this.
2. **Trust boundary.** The LLM has incentive to report what it thinks the prompt wants. Self-reported confidence is systematically biased. Treat as noisy signal, never as authority.
3. **Token overhead.** Asking for JSON metadata adds tokens (input — the instructions to report; output — the metadata itself). For mode 2's economic value (free reasoning on chat subscription), this is mostly fine, but verbose metadata reporting makes chat UX noisier.
4. **Format brittleness.** "Return JSON" is unreliable across models without structured-output enforcement. Need tolerance for malformed/partial blocks, or use provider-specific structured-output mode where available.
5. **Calibration unknown until correlated.** Self-reports might be systematically off. The only way to know is to compare against mode-1 runs of the same prompt where ground-truth telemetry exists. Until then, treat as best-effort.
6. **Multi-call session blindness.** Self-report only narrates what's in the LLM's context at the moment of report. Earlier reasoning that's been compressed or forgotten won't be in the report.

## Honest verdict

The hypothesis is valuable and worth pursuing eventually — but it must be
described accurately. **It does not "close the mode-2 observability gap." It
opens a parallel observability channel for narrated data, while the measured-
data gap remains structurally open.**

A future user looking at a Langfuse trace from a mode-2 run would see: rich
metadata about reasoning and decisions, no tokens/latency/cost. Compared to a
mode-1 trace: rich metadata + tokens/latency/cost. The mode-2 trace is real
observability of a real kind, just thinner than mode-1.

For URGE's product story this is a clean addition: **even mode-2-pure-form
gains some Langfuse visibility**, in the form of decision/reasoning telemetry,
while the technical telemetry gap remains documented as a known structural
limitation. More honest and more useful than either "mode-2 is fully
observable" or "mode-2 is a dark zone."

## Why this note exists at all

The user explicitly asked to capture this for **traceability of the concept**,
not because it's been decided or scoped. The narrated-vs-measured distinction
is the kind of framing that's easy to lose and hard to re-derive once forgotten.
Preserving it now means future-you (or future-collaborators) can pick up the
idea with the framing intact, rather than re-running the investigation.

If/when this gets picked up:

- Treat as an **extension to the Langfuse integration plan**, not a standalone feature.
- Build the metadata-reporting fragment as a normal versioned URGE fragment first; iterate on schema based on real use.
- Add the parser + provenance flag to the MCP `store_result` handler when there's a real prompt using the fragment.
- Don't promise feature parity with mode-1 observability. Document the gap explicitly in user-facing docs.

## Open questions (for if/when this is acted on)

- Should the fragment be language-agnostic or assume a specific LLM (e.g. Claude-style XML tags vs OpenAI structured-output mode)?
- Should self-reported metadata be discoverable in URGE's UI as a separate panel on the Result page (clearly labeled as self-reported), or merged into the existing fields with a provenance icon?
- How should evaluation pipelines treat self-reported confidence? Ignore it? Weight it inversely to its observed calibration over time?
- Is there value in a *required* metadata fragment for certain prompt types (e.g., agent prompts must self-report tool calls)? Or always optional?
- Does the chat-UI-side parsing of `<urge-metadata>` happen in URGE's MCP server (when the chat client calls `store_result`), or is there value in a thin client-side helper?
