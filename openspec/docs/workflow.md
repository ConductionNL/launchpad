# Spec-Driven Development Workflow

## Overview

This workspace uses a spec-driven development workflow that combines:
- **OpenSpec** — Structured specifications alongside code
- **GitHub Issues** — Visual progress tracking via kanban boards
- **Ralph Wiggum loops** — Focused, low-context AI coding iterations
- **Spec verification** — Automated review of code against specifications

The key insight: **specs are written once, then broken into small JSON tasks** that each point back to a specific spec section. This means AI coding loops can work with minimal context (just the task + its spec ref) instead of loading entire spec documents.

## Architecture

```
apps-extra/                         # Workspace root
├── project.md                      # Generic guidelines (all projects)
├── CLAUDE.md                       # Claude Code workflow instructions
├── openspec/                       # SHARED (cross-project)
│   ├── config.yaml                 # Workspace config + shared context
│   ├── schemas/conduction/         # Custom workflow schema + templates
│   ├── specs/                      # Cross-project specs
│   │   ├── nextcloud-app/spec.md   # NC app conventions
│   │   ├── api-patterns/spec.md    # API conventions
│   │   ├── nl-design/spec.md       # Design system compliance
│   │   └── docker/spec.md          # Docker environment
│   └── docs/                       # This documentation
│
├── openregister/                   # PROJECT-SPECIFIC
│   ├── project.md                  # Project description & context
│   └── openspec/
│       ├── config.yaml             # Project config (references shared schema)
│       ├── specs/                  # Domain specs (registers, schemas, objects)
│       └── changes/                # Active changes
│           └── add-feature-x/
│               ├── proposal.md     # Why & what
│               ├── specs/          # Delta specs (ADDED/MODIFIED/REMOVED)
│               ├── design.md       # How (technical approach)
│               ├── tasks.md        # Implementation checklist
│               ├── plan.json       # JSON for Ralph Wiggum (generated)
│               └── review.md       # Verification report (generated)
│
├── opencatalogi/                   # Same pattern...
│   ├── project.md
│   └── openspec/
```

## The Full Flow

### Phase 1: Spec Building

Start by defining what you're building. This creates structured, reviewable specifications.

```
/opsx:new add-woo-search
```

This creates `openspec/changes/add-woo-search/` with metadata. Then either:

**Fast-forward (all at once):**
```
/opsx:ff
```
Creates proposal → specs → design → tasks in dependency order.

**Or incrementally:**
```
/opsx:continue    # Creates proposal
/opsx:continue    # Creates specs
/opsx:continue    # Creates design
/opsx:continue    # Creates tasks
```

**Review the artifacts.** This is your chance to refine requirements before any code is written. The artifacts form a dependency chain:

```
proposal  →  specs   →  design  →  tasks
 (why)       (what)      (how)     (steps)
```

### Phase 2: Plan to GitHub Issues

Once specs are reviewed and approved, convert them to trackable work items:

```
/opsx:plan-to-issues
```

This command:
1. Parses `tasks.md` into structured JSON
2. Creates a **tracking issue** (epic) on GitHub with a full task checklist
3. Creates **individual issues** per task, each containing:
   - Task description
   - Acceptance criteria (from spec scenarios)
   - Spec reference (link to the relevant spec section)
   - Files likely affected
   - Labels: `openspec`, `<change-name>`
4. Saves `plan.json` with all GitHub issue numbers linked

**Why GitHub Issues?**
- Visual kanban board (GitHub Projects)
- Progress visible to the whole team
- Each issue links back to specs for traceability
- Can be managed independently of Claude sessions

### Phase 3: Implementation (Ralph Wiggum Loops)

Start the focused implementation loop:

```
/opsx:ralph-start
```

Each iteration of the loop:
1. **Reads plan.json** — finds the next pending task
2. **Reads ONLY the referenced spec section** — via `spec_ref` pointer
3. **Implements the task** — following acceptance criteria
4. **Updates progress** — marks task done in plan.json and tasks.md
5. **Closes the GitHub issue** — with a summary comment
6. **Moves to the next task** — or stops if all done

**Why this works:**
- Minimal context per iteration (just the task + its spec section)
- No "amnesia" — plan.json tracks state across sessions
- Visual progress — GitHub issues close as work completes
- Resumable — if interrupted, picks up where it left off

### Phase 4: Review

After all tasks are complete, verify the implementation:

```
/opsx:ralph-review
```

This command:
1. Reads ALL spec requirements (ADDED/MODIFIED/REMOVED)
2. Checks each against the actual implementation
3. Cross-references with shared specs (NC conventions, API patterns, etc.)
4. Categorizes findings:
   - **CRITICAL** — Must fix (spec requirement not met)
   - **WARNING** — Should fix (partial compliance)
   - **SUGGESTION** — Nice to have
5. Generates `review.md` in the change directory
6. Creates a GitHub issue if CRITICAL/WARNING findings exist

### Phase 5: Archive

Once review passes:

```
/opsx:archive
```

This:
- Merges delta specs into the main `openspec/specs/` directory
- Moves the change to `openspec/changes/archive/YYYY-MM-DD-<name>/`
- Preserves full audit trail

## The plan.json Format

```json
{
  "change": "add-woo-search",
  "project": "opencatalogi",
  "repo": "ConductionNL/opencatalogi",
  "created": "2026-02-14T12:00:00Z",
  "tracking_issue": 42,
  "tasks": [
    {
      "id": 1,
      "title": "Add search API endpoint",
      "description": "Create /api/woo/search endpoint with query parameter support",
      "github_issue": 43,
      "status": "pending",
      "spec_ref": "openspec/specs/search/spec.md#requirement-search-api",
      "acceptance_criteria": [
        "GIVEN a search query WHEN GET /api/woo/search?q=test THEN returns matching publications",
        "GIVEN no results WHEN searching THEN returns empty array with 200"
      ],
      "files_likely_affected": [
        "lib/Controller/SearchController.php",
        "lib/Service/SearchService.php"
      ],
      "labels": ["openspec", "add-woo-search"]
    }
  ]
}
```

**Key design decisions:**
- `spec_ref` uses `file#anchor` format so the AI can read just that section
- `acceptance_criteria` are extracted from spec scenarios, ready for verification
- `files_likely_affected` scopes the search space for implementation
- `github_issue` enables automatic close on completion
- `status` tracks progress across sessions (`pending` → `in_progress` → `completed`)

## Spec Writing Guide

### RFC 2119 Keywords
- **MUST / SHALL** — Absolute requirement
- **SHOULD** — Recommended, exceptions must be justified
- **MAY** — Optional

### Scenario Format (Gherkin-style)
```markdown
#### Scenario: User searches for publications
- GIVEN a user on the search page
- WHEN they enter "test" in the search field
- THEN the results list MUST show matching publications
- AND the results MUST be paginated (25 per page)
```

### Delta Specs
For changes to existing behavior, use delta sections:

```markdown
## ADDED Requirements
### Requirement: Full-Text Search
The system MUST support full-text search across publication titles and bodies.

## MODIFIED Requirements
### Requirement: Pagination
The system MUST use cursor-based pagination instead of offset pagination.
(Previously: offset-based pagination with page/limit parameters)

## REMOVED Requirements
### Requirement: Legacy Search
(Deprecated: replaced by full-text search)
```

## Commands Reference

| Command | Phase | Description |
|---------|-------|-------------|
| `/opsx:new <name>` | Spec | Start a new change |
| `/opsx:ff` | Spec | Fast-forward all artifacts |
| `/opsx:continue` | Spec | Create next artifact |
| `/opsx:plan-to-issues` | Plan | Tasks → JSON + GitHub Issues |
| `/opsx:ralph-start` | Implement | Start Ralph Wiggum loop from plan.json |
| `/opsx:ralph-review` | Review | Verify code against specs |
| `/opsx:verify` | Review | OpenSpec built-in verification |
| `/opsx:archive` | Archive | Complete and preserve change |

## Tips

- **Start small**: Try the flow on a small feature first to build muscle memory
- **Review specs before coding**: The spec review is the most valuable step — catch issues before writing code
- **Keep tasks small**: Each task should be completable in one Ralph Wiggum iteration (15-30 min of focused work)
- **Use shared specs**: Reference cross-project specs in your delta specs to avoid reinventing patterns
- **Trust the JSON**: The plan.json is your source of truth during implementation — it survives context window resets
- **GitHub is your dashboard**: Use GitHub Projects to visualize progress across multiple changes and projects
