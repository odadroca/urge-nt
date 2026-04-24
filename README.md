# URGE

**A prompt registry with version control, built for LLMs and the humans who wrangle them.**

URGE gives your prompts a home. Store them, version them, organize them -- then let any LLM pull what it needs via API or MCP. Results come back and get archived automatically. Think of it as Git for prompts, with a built-in results archive.

Self-hosted. SQLite. No cloud dependency. Runs on a single machine.

## Why?

Prompts are code, but we treat them like throwaway chat messages. They get lost in conversation history, scattered across projects, copy-pasted between tools with no versioning and no way to track what worked.

URGE flips the direction: instead of you pushing prompts to LLMs, **LLMs pull prompts from URGE**. Your prompts live in one place. Every version is preserved. Every result is archived. Any LLM that speaks HTTP or MCP can tap into the registry.

## Features

- **Version control** -- immutable versions with auto-numbering, branching, and diff comparison
- **Template engine** -- `{{variables}}` for substitution, `{{>slug}}` for includes, recursive resolution
- **MCP server** -- 29 tools, Streamable HTTP + stdio transports, works with Claude Desktop and Claude.ai out of the box
- **REST API** -- full CRUD, Bearer token auth, OpenAPI 3.1 spec included
- **React UI** -- Browse, Pipelines, Teams, Canvas (graph view), Workspace (3-panel editor), and Settings
- **Workspace editor** -- inline autocomplete (`{{` variables, `{{>` fragments), visual composer (drag-drop blocks), version diff viewer (word/char mode), live preview
- **Result archive** -- every LLM response stored with provider, model, ratings, and notes
- **Evaluation** -- LLM-powered scoring across 6 dimensions, composite scores, auto-evaluate option
- **Pipelines** -- run the same prompt through multiple channels with different providers and contexts. Channel system prompts support `{{>slug}}` includes for versioned personas and instructions
- **OAuth 2.1** -- PKCE, refresh tokens with rotation, scoped tokens, Dynamic Client Registration
- **Collections** -- curate prompt versions and results into shareable, nestable groups
- **Teams and namespaces** -- private-by-default prompts with team sharing and `{username}/{slug}` URLs

## Quick Start

```bash
git clone https://github.com/odadroca/urge-nt.git && cd urge-nt
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm run build && php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and register. The first user becomes admin.

For development with HMR: `composer dev`

## How It Works

Here is a typical flow -- a human creates a prompt, then an LLM uses it:

```
1. You create a prompt in the UI          POST /api/v1/prompts
   "Summarize this PR: {{diff}}"          (or just use the editor)

2. You iterate on it                      Each save creates an immutable version
   Fix the wording, save again            v1, v2, v3...

3. Claude pulls the prompt via MCP        get_prompt(slug: "summarize-pr")
   URGE resolves variables + includes     Returns the rendered template

4. Claude sends back the result           store_result(slug: "summarize-pr",
   URGE archives it with metadata           response_text: "...", model: "opus")

5. You review, rate, compare results      All in the Workspace UI
```

The prompt lives in URGE. The LLM never needs to remember it. You can swap models, compare outputs, and track what worked -- all in one place.

## MCP Integration

URGE works as a remote MCP server. Claude Desktop and Claude.ai connect via OAuth automatically:

```json
{
  "mcpServers": {
    "urge": {
      "url": "https://your-urge-instance.com/api/v1/mcp"
    }
  }
}
```

For local development with Claude Code (stdio, no auth):

```json
{
  "mcpServers": {
    "urge": {
      "command": "php",
      "args": ["artisan", "urge:mcp-server", "--user=1"],
      "cwd": "/path/to/urge"
    }
  }
}
```

29 tools cover prompts, results, evaluation, pipelines, branches, and teams. See the [MCP client setup guide](documentation/mcp-clients.md) for complete configuration details including Mistral Le Chat.

## Tech Stack

| | |
|---|---|
| Backend | Laravel 12 / PHP 8.3+ |
| Frontend | React 19, React Query, @xyflow/react, Tailwind CSS |
| Database | SQLite |
| Build | Vite 7 |
| Tests | 385 passing (PHPUnit 11) |

## Documentation

| | |
|---|---|
| [Installation Guide](documentation/install.md) | Detailed setup and deployment |
| [Architecture](documentation/architecture.md) | Data model, services, component hierarchy |
| [MCP Client Setup](documentation/mcp-clients.md) | Claude Desktop, Claude.ai, Mistral Le Chat, stdio |
| [API Reference (Claude Skill)](documentation/claude-skill.md) | API usage examples for LLM integration |
| [OpenAPI Spec](public/openapi.json) | Full API spec, importable as a CustomGPT Action |

## License

[MIT](LICENSE)
