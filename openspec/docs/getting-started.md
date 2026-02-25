# Getting Started

This guide walks you through setting up the spec-driven development workflow and completing your first change.

## Prerequisites

- **Node.js 20+** (required by OpenSpec CLI)
- **Claude Code** installed and configured
- **GitHub CLI** (`gh`) authenticated, or the GitHub MCP server active
- Access to the `ConductionNL` GitHub organization
- The `apps-extra` workspace cloned with at least one project

## Step 1: Install OpenSpec

```bash
npm install -g @fission-ai/openspec@latest
```

Verify installation:

```bash
openspec --version
```

## Step 2: Understand the Workspace Structure

The workspace has two levels of spec management:

### Workspace level (shared)

```
apps-extra/
├── project.md              # Coding standards for ALL projects
├── openspec/
│   ├── config.yaml         # Shared context and rules
│   ├── schemas/conduction/ # Our custom workflow schema
│   ├── specs/              # Cross-project specs (NC conventions, APIs, etc.)
│   └── docs/               # You are here
```

These files define the patterns and conventions that apply to every project.

### Project level (specific)

```
openregister/
├── project.md              # What this project does, its architecture, dependencies
├── openspec/
│   ├── config.yaml         # Project config (points to shared schema)
│   ├── specs/              # Domain-specific specs for this project
│   └── changes/            # Active work in progress
```

Each project has its own specs describing its unique domain behavior.

## Step 3: Initialize a New Project (if needed)

If your project doesn't have OpenSpec set up yet, see [Adding a Project](./adding-a-project.md).

If you're working on `openregister` or `opencatalogi`, they're already initialized.

## Step 4: Your First Change

Let's walk through creating your first spec-driven change.

### 4a. Start a new change

Navigate to your project and run:

```
/opsx:new my-first-feature
```

This creates `openspec/changes/my-first-feature/` with a `.openspec.yaml` metadata file.

### 4b. Build the specs

Generate all planning artifacts at once:

```
/opsx:ff
```

Claude will create:
1. **`proposal.md`** — Why this change exists and what it covers
2. **`specs/*.md`** — Detailed requirements with scenarios
3. **`design.md`** — Technical approach and architecture
4. **`tasks.md`** — Implementation checklist

### 4c. Review the artifacts

Read through each artifact. This is the most valuable step — catching issues in specs is much cheaper than catching them in code.

Things to check:
- Does the proposal cover the right scope?
- Are the spec requirements using the right RFC 2119 keywords (MUST vs SHOULD)?
- Do the scenarios cover edge cases?
- Is the task breakdown granular enough?

Edit the artifacts directly if needed — they're just markdown files.

### 4d. Create GitHub Issues

```
/opsx:plan-to-issues
```

This converts your tasks into GitHub Issues:
- A **tracking issue** with a full checklist (your "epic")
- **Individual issues** per task with acceptance criteria and spec references
- A **`plan.json`** file linking everything together

Open the tracking issue URL to see your kanban view.

### 4e. Start implementing

```
/opsx:ralph-start
```

This starts a focused implementation loop. Each iteration:
1. Picks the next pending task from `plan.json`
2. Reads ONLY the spec section that task references
3. Implements the task
4. Closes the GitHub issue
5. Moves to the next task

The key benefit: each iteration works with minimal context, preventing AI "amnesia" on large changes.

### 4f. Review your work

After all tasks are done:

```
/opsx:ralph-review
```

This checks every spec requirement against your implementation and reports:
- **CRITICAL** findings that must be fixed
- **WARNING** findings that should be addressed
- **SUGGESTION** findings that are nice-to-have

### 4g. Archive the change

Once review passes:

```
/opsx:archive
```

This merges your delta specs into the main specs and preserves the change for history.

## Quick Reference

| What you want to do | Command |
|---------------------|---------|
| Start a new feature | `/opsx:new <name>` |
| Generate all specs at once | `/opsx:ff` |
| Generate specs one at a time | `/opsx:continue` |
| Convert tasks to GitHub Issues | `/opsx:plan-to-issues` |
| Start implementing | `/opsx:ralph-start` |
| Review implementation | `/opsx:ralph-review` |
| Complete and archive | `/opsx:archive` |

## Next Steps

- Read the [Command Reference](./commands.md) for detailed options on each command
- Read [Writing Specs](./writing-specs.md) to write better specifications
- See the [Walkthrough](./walkthrough.md) for a full end-to-end example
- See [Adding a Project](./adding-a-project.md) to onboard a new project
