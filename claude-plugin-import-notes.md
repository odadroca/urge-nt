# Claude Plugin Import — Design Notes

> Status: thinking note, not a scoped plan. Captured for later iteration.
> Companion to `langfuse-integration-plan.md` — both extend URGE's
> orchestrator/registry positioning to a new content type.

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
| Plugin itself (the bundle) | `Collection` | Perfect — curated polymorphic grouping is what Collections are |
| Hooks (shell commands) | — | Out of scope; not prompts |
| MCP servers (connection config) | — | Out of scope; not prompts |

~70% of plugin content maps directly to existing primitives. The
plugin-as-Collection insight is the unlock: `CollectionItem` is already
polymorphic, so a plugin-Collection holds skills + commands + agents
heterogeneously, and nested collections (already supported) give
plugin-packs-of-plugins if that ever matters.

## Why it's valuable (beyond "I can import things")

Import is a means; the real wins only appear once plugin content lives in URGE:

1. **Version control with branches.** Plugin skills evolve informally today, typically via commits on a git repo. URGE's branch/pin model is better suited to *experimental* skill iteration (try variant, measure, promote).
2. **Fragment composability.** URGE's `{{>slug}}` lets multiple skills share a common "output format" or "tone" fragment. Plugins don't natively have this — authors copy-paste.
3. **Run + archive.** URGE can execute a skill against a provider and store Results. Plugin skills are tested today by "run Claude Code and hope." URGE gives a proper testbed with history.
4. **Teams.** Plugins are usually solo-authored or PR'd. URGE's team model lets a group iterate privately before publishing.
5. **Cross-plugin distillation.** The new thing. Synthesize across imported plugins. Genuinely novel value.

## Orchestrator framing

Parallel to the Langfuse story: URGE doesn't *run* the plugin (Claude Code
does), but URGE owns the *content primitives the plugin is built from*. Claude
Code is the execution engine; URGE is the authoring + registry + distillation
layer. Same pattern as URGE/Langfuse: URGE is a peer, not an add-on. Each tool
stays in its strength.

## MVP scope (when this gets picked up)

- **Import-only.** Parser for `plugin.json` + `skills/`, `commands/`, `agents/` dirs → Prompts + a wrapping Collection. Defer round-trip export.
- **Source tracking column** — `Prompt.source_url` nullable (repo URL + commit hash + file path). Cheap; enables diffing/re-import later without committing to full sync.
- **Tags over new type enum.** Use `tags: [skill, agent, command]` rather than expanding `Prompt.type` beyond `prompt|fragment`. Less schema churn. Promote to a dedicated type only if behavior genuinely diverges.
- **CLI first, MCP tool second.** `php artisan urge:import-plugin <path-or-url>` mirrors the existing `urge:import-v1` command. Add an `import_plugin` MCP tool once the human workflow is validated.
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
- The mapping I described is structural. The semantics may surprise — e.g., a slash command's "argument hint" frontmatter key has no obvious URGE analog. Discover these case-by-case during implementation, don't pre-design for them.

## Open design questions (for later)

- Does an imported plugin become *one* Collection per plugin, or one Collection per plugin-version? (Probably per-plugin, with PromptVersion handling content evolution.)
- How are agent tool-allowlists stored? New `Prompt.metadata` JSON column, or piggyback on `variable_metadata`?
- Does importing the same plugin twice update existing Prompts or create new ones? (Probably: by `source_url` match → new PromptVersion on existing Prompt; otherwise create new.)
- Should the import respect plugin git history and create one PromptVersion per source commit? (Probably no for MVP — too clever. Single version representing "as-of-import" is fine.)
