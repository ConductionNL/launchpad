# Command Reference

Complete reference for all commands available in the spec-driven development workflow.

## OpenSpec Built-in Commands

These commands are installed per-project when you run `openspec init`. They're available inside each project directory.

---

### `/opsx:new <change-name>`

**Phase:** Spec Building

Start a new change. Creates the change directory with metadata.

**Usage:**
```
/opsx:new add-publication-search
```

**What it creates:**
```
openspec/changes/add-publication-search/
└── .openspec.yaml      # Change metadata (schema, created date)
```

**Tips:**
- Use descriptive kebab-case names: `add-dark-mode`, `fix-cors-headers`, `refactor-object-service`
- The name becomes a GitHub Issue label, so keep it readable

---

### `/opsx:ff`

**Phase:** Spec Building

Fast-forward: generates ALL artifacts in dependency order (proposal → specs → design → tasks) in one go.

**Usage:**
```
/opsx:ff
```

**What it creates:**
```
openspec/changes/add-publication-search/
├── .openspec.yaml
├── proposal.md         # Why & what
├── specs/              # Delta specs (ADDED/MODIFIED/REMOVED)
│   └── search/
│       └── spec.md
├── design.md           # How (technical approach)
└── tasks.md            # Implementation checklist
```

**When to use:** When you have a clear idea of what you want to build and want to generate everything quickly for review.

**When NOT to use:** When you want to iterate on each artifact step by step, getting feedback between each. Use `/opsx:continue` instead.

---

### `/opsx:continue`

**Phase:** Spec Building

Creates the next artifact in the dependency chain. Run repeatedly to build specs incrementally.

**Usage:**
```
/opsx:continue    # Creates proposal.md (first time)
/opsx:continue    # Creates specs/ (second time)
/opsx:continue    # Creates design.md (third time)
/opsx:continue    # Creates tasks.md (fourth time)
```

**Dependency chain:**
```
proposal (root)
    ├── specs (requires: proposal)
    ├── design (requires: proposal)
    └── tasks (requires: specs + design)
```

**When to use:** When you want to review and refine each artifact before proceeding to the next.

---

### `/opsx:explore`

**Phase:** Pre-spec

Think through ideas and investigate the codebase before starting a formal change. No artifacts are created.

**Usage:**
```
/opsx:explore
```

**When to use:** When you're not sure what approach to take yet and want to investigate first.

---

### `/opsx:apply`

**Phase:** Implementation

OpenSpec's built-in implementation command. Reads `tasks.md` and works through tasks.

**Usage:**
```
/opsx:apply
```

**Note:** For our workflow, prefer `/opsx:ralph-start` instead — it uses `plan.json` for minimal context and integrates with GitHub Issues.

---

### `/opsx:verify`

**Phase:** Review

OpenSpec's built-in verification. Validates implementation against artifacts.

**Usage:**
```
/opsx:verify
```

**Checks:**
- **Completeness** — All tasks done, all requirements implemented
- **Correctness** — Implementation matches spec intent
- **Coherence** — Design decisions reflected in code

**Note:** For our workflow, prefer `/opsx:ralph-review` which also cross-references shared specs and creates GitHub Issues for findings.

---

### `/opsx:sync`

**Phase:** Archive

Merges delta specs from the change into the main `openspec/specs/` directory.

**Usage:**
```
/opsx:sync
```

**What it does:**
- **ADDED** requirements → appended to main spec
- **MODIFIED** requirements → replace existing in main spec
- **REMOVED** requirements → deleted from main spec

Usually done automatically during archive.

---

### `/opsx:archive`

**Phase:** Archive

Complete a change and preserve it for the historical record.

**Usage:**
```
/opsx:archive
```

**What it does:**
1. Checks artifact and task completion
2. Syncs delta specs into main specs (if not already done)
3. Moves the change to `openspec/changes/archive/YYYY-MM-DD-<name>/`
4. All artifacts are preserved for audit trail

---

### `/opsx:bulk-archive`

**Phase:** Archive

Archive multiple completed changes at once.

**Usage:**
```
/opsx:bulk-archive
```

**When to use:** When you have several changes that are all complete and want to clean up.

---

### `/opsx:onboard`

**Phase:** Setup

Get an overview of the current project's OpenSpec setup and active changes.

**Usage:**
```
/opsx:onboard
```

---

## Custom Conduction Commands

These commands are workspace-level and available from any project within `apps-extra/`. They extend OpenSpec with GitHub Issues integration and Ralph Wiggum loops.

---

### `/opsx:plan-to-issues`

**Phase:** Planning → GitHub

Converts an OpenSpec change's `tasks.md` into structured `plan.json` and creates corresponding GitHub Issues.

**Usage:**
```
/opsx:plan-to-issues
```

**Prerequisites:**
- A change with completed `tasks.md`
- GitHub MCP server active or `gh` CLI authenticated
- Git remote pointing to a ConductionNL repository

**What it does:**

1. **Finds the active change** in the current project's `openspec/changes/`
2. **Detects the GitHub repo** from `git remote get-url origin`
3. **Parses tasks.md** into structured JSON
4. **Creates GitHub Issues:**
   - One **tracking issue** (epic) with:
     - Title: `[OpenSpec] <change-name>`
     - Body: proposal summary + task checklist
     - Labels: `openspec`, `tracking`
   - One **issue per task** with:
     - Title: `[<change-name>] <task title>`
     - Body: description, acceptance criteria, spec ref, affected files
     - Labels: `openspec`, `<change-name>`
5. **Saves `plan.json`** with all issue numbers linked

**Output example:**
```
Created tracking issue: https://github.com/ConductionNL/opencatalogi/issues/42
Created 5 task issues: #43, #44, #45, #46, #47
Saved plan.json at: openspec/changes/add-search/plan.json

Run /opsx:ralph-start to begin implementation.
```

**The plan.json it creates:**
```json
{
  "change": "add-search",
  "project": "opencatalogi",
  "repo": "ConductionNL/opencatalogi",
  "created": "2026-02-15T10:00:00Z",
  "tracking_issue": 42,
  "tasks": [
    {
      "id": 1,
      "title": "Create SearchController",
      "description": "Add new controller for search API endpoint",
      "github_issue": 43,
      "status": "pending",
      "spec_ref": "openspec/specs/search/spec.md#requirement-search-api",
      "acceptance_criteria": [
        "GIVEN a search query WHEN GET /api/search?q=test THEN returns matching results"
      ],
      "files_likely_affected": [
        "lib/Controller/SearchController.php"
      ],
      "labels": ["openspec", "add-search"]
    }
  ]
}
```

---

### `/opsx:ralph-start`

**Phase:** Implementation

Starts a Ralph Wiggum implementation loop driven by `plan.json`. This is the core of our minimal-context coding approach.

**Usage:**
```
/opsx:ralph-start
```

**Prerequisites:**
- A `plan.json` in the active change (created by `/opsx:plan-to-issues`)

**What it does per iteration:**

1. **Reads plan.json** — finds the next task with `"status": "pending"`
2. **Sets status to `"in_progress"`** in plan.json
3. **Reads ONLY the referenced spec section** — uses `spec_ref` to load just the relevant requirement, NOT the entire spec file
4. **Implements the task** — following acceptance criteria and coding standards
5. **Verifies** — checks acceptance criteria are met
6. **Updates progress:**
   - Sets task to `"completed"` in plan.json
   - Checks off boxes in tasks.md
   - Closes the GitHub issue with a summary comment
   - Updates the tracking issue checklist
7. **Loops** — picks up the next pending task, or stops if all done

**Why minimal context matters:**

Each iteration loads only:
- `plan.json` (the task list — typically 1-2 KB)
- One spec section via `spec_ref` (the specific requirement — a few paragraphs)
- The affected files

It does NOT load:
- proposal.md
- design.md
- Other spec files
- The full tasks.md

This prevents context window bloat and keeps each iteration fast and focused.

**Resuming after interruption:**

If the loop is interrupted (context limit, error, etc.), simply run `/opsx:ralph-start` again. It reads `plan.json`, finds the first non-completed task, and continues from there.

---

### `/opsx:ralph-review`

**Phase:** Review

Verifies the completed implementation against all spec requirements and shared conventions. Creates a structured review report.

**Usage:**
```
/opsx:ralph-review
```

**Prerequisites:**
- All tasks in plan.json should be `"completed"`

**What it does:**

1. **Loads full context** — proposal, all delta specs, tasks, plan.json
2. **Checks completeness:**
   - All tasks completed?
   - All GitHub issues closed?
   - All task checkboxes checked?
3. **Checks spec compliance:**
   - For each ADDED requirement: does the implementation exist?
   - For each MODIFIED requirement: is the old behavior changed?
   - For each REMOVED requirement: is the deprecated code gone?
   - Do GIVEN/WHEN/THEN scenarios match the code behavior?
4. **Cross-references shared specs:**
   - `nextcloud-app/spec.md` — correct app structure, DI, route ordering
   - `api-patterns/spec.md` — URL patterns, CORS, error responses
   - `nl-design/spec.md` — design tokens, accessibility
   - `docker/spec.md` — environment compatibility
5. **Categorizes findings:**
   - **CRITICAL** — Spec MUST/SHALL requirement not met
   - **WARNING** — SHOULD requirement not met or partial compliance
   - **SUGGESTION** — Improvement opportunity
6. **Generates `review.md`** in the change directory
7. **Creates GitHub Issue** if CRITICAL/WARNING findings exist

**Output example:**
```
Review: add-search
Tasks completed: 5/5
GitHub issues closed: 5/5
Spec compliance: PASS (with warnings)

Findings:
- 0 CRITICAL
- 2 WARNING
  - Missing CORS headers on /api/search (api-patterns spec)
  - No pagination metadata in response (api-patterns spec)
- 1 SUGGESTION
  - Consider adding rate limiting

Review saved: openspec/changes/add-search/review.md
GitHub issue created: #48 [Review] add-search: 0 critical, 2 warnings
```

---

## OpenSpec CLI Commands

These are terminal commands (not Claude slash commands) for managing specs directly.

| Command | Description |
|---------|-------------|
| `openspec init --tools claude` | Initialize OpenSpec in a project |
| `openspec list --changes` | List all active changes |
| `openspec list --specs` | List all specs |
| `openspec show <name>` | View details of a change or spec |
| `openspec status --change <name>` | Show artifact completion status |
| `openspec validate --all` | Validate all specs and changes |
| `openspec validate --strict` | Strict validation (errors on warnings) |
| `openspec update` | Regenerate AI tool config after CLI upgrade |
| `openspec schema which` | Show which schema is being used |
| `openspec config list` | Show all configuration |

Add `--json` to any command for machine-readable output.

---

## Command Flow Cheat Sheet

```
/opsx:explore           (optional: investigate first)
       │
       ▼
/opsx:new <name>        (start a change)
       │
       ▼
/opsx:ff                (generate all specs)
       │                   OR
/opsx:continue          (generate specs one by one)
       │
       ▼
  [Human review & edit specs]
       │
       ▼
/opsx:plan-to-issues    (tasks → JSON + GitHub Issues)
       │
       ▼
/opsx:ralph-start       (implement via focused loops)
       │
       ▼
/opsx:ralph-review      (verify against specs)
       │
       ▼
/opsx:archive           (complete & preserve)
```
