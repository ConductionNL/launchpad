# Adding a New Project

Guide for onboarding a new project into the spec-driven development workflow.

## Prerequisites

- The project is cloned into `apps-extra/`
- OpenSpec CLI is installed (`npm install -g @fission-ai/openspec@latest`)
- The project has a git remote pointing to a ConductionNL GitHub repo

## Step 1: Initialize OpenSpec

Navigate to the project directory and run:

```bash
cd apps-extra/<project-name>
openspec init --tools claude
```

This creates:
- `openspec/` directory with `specs/`, `changes/`, and metadata
- `.claude/commands/opsx/` — 10 slash commands (new, ff, continue, apply, etc.)
- `.claude/skills/openspec-*/` — 10 skills for Claude Code

## Step 2: Configure the Shared Schema

Replace the auto-generated `openspec/config.yaml` with one that references our shared schema:

```yaml
schema: conduction

context: |
  Project: <Project Name>
  Repo: ConductionNL/<repo-name>
  Type: Nextcloud App (PHP)  # or Frontend (React), etc.
  Description: <one-line description>
  Key components: <list main components>
  Database: PostgreSQL (via OpenRegister's ObjectService)  # or direct, etc.
  Mount path: /var/www/html/custom_apps/<appname>

  Shared specs: See ../openspec/specs/ for cross-project conventions
  Project guidelines: See ../project.md for workspace-wide standards

rules:
  proposal:
    - Reference shared nextcloud-app spec for app structure requirements
    - <add project-specific proposal rules>
  specs:
    - <add project-specific spec rules>
  design:
    - <add project-specific design rules>
  tasks:
    - <add project-specific task rules>
```

### Key fields to customize:

- **`context`**: Describe the project's purpose, tech stack, and architecture. This context is injected into every artifact Claude generates.
- **`rules`**: Add project-specific rules per artifact type. These guide Claude when writing specs for this project.

## Step 3: Create project.md

Create `<project-name>/project.md` describing the project. Use this template:

```markdown
# <Project Name>

## Overview
<What this project does and why it exists>

## Repository
- **GitHub**: https://github.com/ConductionNL/<repo-name>
- **Organization**: ConductionNL
- **Container mount**: /var/www/html/custom_apps/<appname>

## Architecture

### Key Components
- **<Component A>** — <description>
- **<Component B>** — <description>

### Important Patterns
- <Pattern or gotcha worth knowing>

### Directory Structure
\```
lib/
  Controller/
  Service/
  Db/
appinfo/
  info.xml
  routes.php
\```

## Dependencies
- **Depends on**: <list upstream dependencies>
- **Depended on by**: <list downstream dependents>

## API
- Base URL: `/index.php/apps/<appname>/api/`
- Auth: <authentication method>
- Format: JSON

## Testing
- <How to run tests for this project>
```

### Tips for a good project.md:

- List known gotchas (e.g., "route ordering matters", "ObjectService takes array not string")
- List dependencies in both directions (what you depend on AND what depends on you)
- Include any project-specific coding patterns that differ from the generic standards

## Step 4: Create Initial Specs (Optional)

If the project already has existing functionality, document it in specs:

```
openspec/specs/
├── <domain-a>/
│   └── spec.md
├── <domain-b>/
│   └── spec.md
```

Use the format from the [Writing Specs](./writing-specs.md) guide. Start with the most important capabilities — you don't need to document everything at once.

For a new project, you can skip this and let specs accumulate naturally as you make changes.

## Step 5: Verify Setup

Run the OpenSpec CLI to verify everything is valid:

```bash
openspec validate --all
openspec config list
openspec schema which
```

Then test the Claude integration:

```
/opsx:onboard
```

This should show the project's OpenSpec setup and confirm the shared schema is being used.

## Step 6: Link to Shared Specs

The shared specs in `apps-extra/openspec/specs/` apply to your project automatically through the `conduction` schema rules. When writing delta specs for changes, reference them:

```markdown
## ADDED Requirements

### Requirement: New API Endpoint
The system MUST provide a REST endpoint at `/api/<resource>`.
See: `../../openspec/specs/api-patterns/spec.md#requirement-url-structure`
```

Available shared specs:
- `nextcloud-app/spec.md` — App structure, DI, route ordering, config storage
- `api-patterns/spec.md` — URL patterns, CORS, auth, pagination, error responses
- `nl-design/spec.md` — Design tokens, theme compatibility, accessibility
- `docker/spec.md` — Development environment conventions

## Checklist

After completing the steps above, you should have:

- [ ] `openspec/` directory initialized
- [ ] `openspec/config.yaml` pointing to `conduction` schema with project context
- [ ] `.claude/commands/opsx/` with 10 commands
- [ ] `.claude/skills/openspec-*/` with 10 skills
- [ ] `project.md` describing the project
- [ ] `openspec validate --all` passes
- [ ] `/opsx:onboard` works in Claude Code

## Example: Real Projects

See these already-onboarded projects for reference:

- **openregister**: `apps-extra/openregister/openspec/config.yaml` and `apps-extra/openregister/project.md`
- **opencatalogi**: `apps-extra/opencatalogi/openspec/config.yaml` and `apps-extra/opencatalogi/project.md`
