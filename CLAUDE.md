# Workflow

## Before Starting Any Task
1. **Ask clarifying questions one at a time** before writing any code or making changes. Ask up to 5 questions about scope, requirements, constraints, edge cases, and expected behavior — but ask them individually, waiting for the answer before asking the next. Stop early if you have enough clarity.
2. **Present a plan** based on the answers. The plan should outline the approach, files to change, and any risks or trade-offs.
3. **Wait for approval** of the plan before proceeding with implementation.

## Spec-Driven Development (OpenSpec)

This workspace uses **OpenSpec** for structured, spec-driven development. Specs live alongside code and persist across sessions.

### Key Files
- **`project.md`** (root) — Generic guidelines for all projects
- **`{project}/project.md`** — Project-specific context and architecture
- **`openspec/`** (root) — Shared schemas and cross-project specs
- **`{project}/openspec/`** — Project-specific specs and active changes

### Development Flow

For any non-trivial feature or change, follow this flow:

1. **Spec Building** — Define what to build before writing code
   - `/opsx:new <change-name>` — Start a new change
   - `/opsx:ff` — Fast-forward: create all artifacts (proposal → specs → design → tasks)
   - Or use `/opsx:continue` to create artifacts incrementally
   - Human reviews and refines the specs

2. **Plan to GitHub Issues** — Convert tasks to trackable issues
   - `/opsx:plan-to-issues` — Parses tasks.md into `plan.json` + creates GitHub Issues
   - Creates a tracking issue (epic) with task checklist
   - Creates individual issues per task, labeled `openspec`
   - Gives you a visual kanban/project board view of progress

3. **Implementation via Ralph Wiggum** — Focused, low-context coding loops
   - `/opsx:ralph-start` — Starts a Ralph Wiggum loop from plan.json
   - Each iteration loads ONLY the relevant spec section (minimal context)
   - Auto-closes GitHub issues as tasks complete
   - Auto-updates the tracking issue checklist

4. **Review Against Specs** — Verify implementation matches specs
   - `/opsx:ralph-review` — Checks code against all spec requirements
   - Categorizes findings as CRITICAL / WARNING / SUGGESTION
   - Creates a review GitHub issue if problems found
   - Generates `review.md` in the change directory

5. **Archive** — Complete and preserve the change
   - `/opsx:archive` — Merges delta specs into main specs, archives the change

### plan.json Format
The `plan.json` bridges specs and implementation. Each task contains:
- `spec_ref` — Points to the exact spec section
- `acceptance_criteria` — From spec scenarios (GIVEN/WHEN/THEN)
- `github_issue` — Linked GitHub issue number
- `files_likely_affected` — Scope of changes

### Shared Specs
Cross-project specs in `openspec/specs/` define shared conventions:
- `nextcloud-app/spec.md` — App structure, DI, routes
- `api-patterns/spec.md` — URL patterns, CORS, errors
- `nl-design/spec.md` — Design tokens, accessibility
- `docker/spec.md` — Development environment

# Project Context

## Open Register
We are working on the Nextcloud app "Open Register" and a separate UI with nginx pass-through.

### Local Development
- UI: http://localhost:3030
- Backend: http://localhost:80

### Local  Environments
- UI: http://localhost:3000/
- Backend: http://localhost:8080/

## Design System

### NL Design System Support
All apps in this workspace should support the **nldesign** app for consistent government-compliant theming.

- **App**: nldesign (NL Design System theme for Nextcloud)
- **Purpose**: Provides Rijkshuisstijl and other Dutch government design standards
- **Token Sets**: Rijkshuisstijl, Utrecht, Amsterdam, Den Haag, Rotterdam
- **Implementation**: CSS-based theming using design tokens
- **Compliance**: WCAG AA accessibility, Rijkshuisstijl guidelines

**Requirements for app compatibility:**
- Use standard Nextcloud components and CSS classes
- Avoid hardcoded colors (use CSS variables)
- Test with nldesign app enabled
- Ensure proper contrast and readability
- Follow Nextcloud design guidelines

## Docker Environment

**Always use the OpenRegister docker-compose** as the development environment. The compose file lives at `openregister/docker-compose.yml`.

### Starting the environment

**Default (minimal)** — starts only db, Nextcloud, and n8n:
```bash
docker compose -f openregister/docker-compose.yml up -d
```

**With optional service groups** — add `--profile <name>` to include extra services:
```bash
# AI services (presidio, tgi-llm, dolphin-vlm, openanonymiser, exapp-ollama, exapp-openwebui)
docker compose -f openregister/docker-compose.yml --profile ai up -d

# Tilburg WOO UI frontend
docker compose -f openregister/docker-compose.yml --profile ui up -d

# AppAPI infrastructure (HaRP reverse proxy)
docker compose -f openregister/docker-compose.yml --profile exapps up -d

# Multiple profiles at once
docker compose -f openregister/docker-compose.yml --profile ai --profile ui up -d
```

### Available profiles

| Profile | Services | Purpose |
|---------|----------|---------|
| *(default)* | db, nextcloud, exapp-n8n | Core dev environment |
| `ai` | presidio-analyzer, tgi-llm, dolphin-vlm, openanonymiser, exapp-ollama, exapp-openwebui | AI/LLM services |
| `ui` | tilburg-woo-ui | Public WOO document frontend |
| `exapps` | harp | Nextcloud AppAPI reverse proxy |
| `solr` / `search` | solr, zookeeper | Search engine (alternative to pgvector) |
| `elasticsearch` | elasticsearch | Elasticsearch backend |
| `ollama` | ollama (standalone) | Standalone Ollama (not ExApp) |
| `standalone` | n8n, open-webui (standalone) | Standalone versions (not ExApps) |
| `mariadb` | db-mariadb, nextcloud-mariadb | MariaDB compatibility testing |
| `openproject` / `integrations` | openproject | Project management |
| `xwiki` / `integrations` | xwiki | Wiki platform |
| `ox` / `integrations` | open-xchange | Email and groupware |
| `valtimo` / `commonground` | valtimo | BPM and case management |
| `openzaak` / `commonground` | openzaak | ZGW API case management |
| `openklant` / `commonground` | openklant | Customer interaction registry |

### Scripts

- **`clean-env.sh`** — Full environment reset: stops all containers, removes volumes, restarts, and installs core apps (openregister, opencatalogi, softwarecatalog, nldesign, mydash). Run with `bash clean-env.sh` or use the `/clean-env` Claude skill.

## Browser Pool (Playwright MCP)

This workspace has **7 independent Playwright browser sessions** configured as MCP servers. This enables parallel browser usage across the main agent and sub-agents (6 headless for parallel work + 1 headed for user watching).

### Available Browsers
| Server | Mode | Purpose |
|--------|------|---------|
| `browser-1` | Headless | Main agent — default for single-browser tasks |
| `browser-2` | Headless | Sub-agent / parallel task |
| `browser-3` | Headless | Sub-agent / parallel task |
| `browser-4` | Headless | Sub-agent / parallel task |
| `browser-5` | Headless | Sub-agent / parallel task |
| `browser-6` | **Headed** | User wants to watch — visible browser window |
| `browser-7` | Headless | Sub-agent / parallel task |

### Usage Rules

1. **Default**: Use `browser-1` (`mcp__browser-1__*` tools) for normal browser work.
2. **Parallel sub-agents**: When spawning Task sub-agents that each need a browser, assign each a different browser number in the prompt. Example: _"Use `browser-2` tools (e.g. `mcp__browser-2__browser_navigate`) for this task."_
3. **Fallback on error**: If a browser session errors or is unresponsive (e.g. another agent is using it), try the next numbered browser. Cycle through `browser-1` → `browser-2` → ... → `browser-5` → `browser-7`.
4. **User watching**: When the user asks to "look along", "watch", "see the browser", or "follow along", switch to `browser-6`. This is the only headed (visible window) browser. Tell the user you're switching so they know to look at the browser window.
5. **Sub-agent assignment**: When launching N parallel sub-agents that need browsers, assign them `browser-2` through `browser-5` and `browser-7`, keeping `browser-1` free for the main agent and `browser-6` reserved for user watching. For 6 parallel agents: use `browser-2`, `browser-3`, `browser-4`, `browser-5`, `browser-7`, and `browser-1` (if the main agent doesn't need a browser).
6. **Isolated mode**: All browsers run with `--isolated` — profiles are kept in memory only, no state is written to disk. Cookies, sessions, and storage are destroyed when the browser process exits. Each browser process is fully independent.

### Tool Naming Convention
All Playwright tools are prefixed with the server name:
- `mcp__browser-1__browser_navigate`, `mcp__browser-1__browser_snapshot`, etc.
- `mcp__browser-2__browser_navigate`, `mcp__browser-2__browser_snapshot`, etc.
- ...and so on for all 7 browsers.

## Notes
- See `.claude/CLAUDE.local.md` for local credentials and sensitive configuration (not committed to git)
- See `project.md` for detailed workspace-wide coding standards and project list
- See `openspec/docs/workflow.md` for the full spec-driven development workflow documentation
