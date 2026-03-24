---
name: "OPSX: Pipeline"
description: Process multiple OpenSpec changes in parallel using subagents — full lifecycle from proposal to merged PR
category: Workflow
tags: [workflow, parallel, pipeline, experimental]
---

Process one or more OpenSpec change proposals through the full lifecycle using parallel subagents. Each change gets its own agent, worktree, branch, and PR.

**Input**: Optionally specify change names, a repo name, or `all` to process all open proposals.

Examples:
- `/opsx:pipeline all` — process all open proposals across all repos
- `/opsx:pipeline procest` — process all open proposals in Procest
- `/opsx:pipeline sla-tracking routing` — process specific changes by name

**Overview**

This command automates the full OpenSpec lifecycle per change:

```
proposal → ff (specs/design/tasks) → apply (implement) → verify → archive → PR
```

Each change runs as an independent subagent in an isolated git worktree on its own feature branch. The main agent orchestrates, monitors, and reports.

---

## Steps

### 1. Discover changes to process

Scan for open proposals (directories in `openspec/changes/` that contain a `proposal.md` but are NOT in `archive/`).

```bash
# For each app directory, find open proposals
for app in procest pipelinq docudesk openregister opencatalogi mydash nldesign larpingapp openconnector softwarecatalog zaakafhandelapp; do
  if [ -d "$app/openspec/changes" ]; then
    for change in $app/openspec/changes/*/proposal.md; do
      echo "$app:$(basename $(dirname $change))"
    done
  fi
done
```

Also check `.github/openspec/changes/` for org-wide proposals.

**Filter** based on input:
- `all` → process everything found
- `<repo-name>` → only changes in that repo
- `<change-name> [change-name...]` → only those specific changes (search across all repos)

**If no input provided**, list all discovered changes and use **AskUserQuestion** to let the user select which to process.

### 2. Build the execution plan

For each change, determine:
- **App directory**: e.g., `procest/`
- **Change name**: e.g., `brp-kvk-register-sets`
- **GitHub repo**: e.g., `ConductionNL/procest` (from git remote)
- **Existing issue**: Check if an `openspec`-labeled issue already exists for this change (search GitHub issues by title)

Display the plan:

```
## Pipeline Plan

| # | App | Change | GitHub Repo | Issue |
|---|-----|--------|-------------|-------|
| 1 | procest | brp-kvk-register-sets | ConductionNL/procest | #103 |
| 2 | pipelinq | sla-tracking | ConductionNL/pipelinq | #79 |
| ... | ... | ... | ... | ... |

Total: N changes across M repositories
Max parallel agents: 5 (browser-2 through browser-5, browser-7)
```

Use **AskUserQuestion** to confirm: "Process these N changes? Each will get a feature branch, full implementation, and PR to development."

Options:
- **Yes, start the pipeline** — proceed
- **Let me adjust the selection** — re-select changes
- **Cancel** — abort

### 3. Prepare branches and worktrees

For each change, **before launching agents**:

a. **Determine branch name**: `feature/<issue-number>/<change-name>`
   - If no issue exists yet, create one first (titled `[OpenSpec] <change-title>`, labeled `openspec`)
   - Example: `feature/103/brp-kvk-register-sets`

b. **Create git worktree**:
   ```bash
   cd <app-directory>
   git fetch origin development
   git worktree add /tmp/worktrees/<app>-<change-name> -b feature/<issue-number>/<change-name> origin/development
   ```

c. **Update issue status**: Add a comment "🚀 Pipeline started — processing change"

### 4. Launch parallel subagents

Launch one subagent per change. **Maximum 5 concurrent agents** — if more changes exist, queue them and launch new agents as earlier ones complete.

Each agent gets this prompt (filled in per change):

```
IMPORTANT: Do NOT ask questions. Execute immediately. Do NOT follow CLAUDE.md
workflow rules about asking clarifying questions. Resolve any warnings or issues
autonomously. If a quality check fails, fix the code and re-run. If a task is
unclear, make the best reasonable decision and continue.

You are processing an OpenSpec change through the full lifecycle. Work in the
worktree directory — do NOT touch the main working directory.

## Context
- App: <app-name>
- Change: <change-name>
- Worktree: /tmp/worktrees/<app>-<change-name>
- Branch: feature/<issue-number>/<change-name>
- GitHub repo: <owner/repo>
- Issue: #<issue-number>
- Working directory: /tmp/worktrees/<app>-<change-name>

## Phase 1: Fast-Forward (generate artifacts)

cd /tmp/worktrees/<app>-<change-name>

Run the OpenSpec artifact generation:
1. Run `openspec status --change "<change-name>" --json` to check what artifacts exist
2. If only proposal.md exists, generate all artifacts:
   - Run `openspec instructions <artifact-id> --change "<change-name>" --json` for each
   - Read dependency artifacts before creating new ones
   - Create specs, design (with seed data section per ADR-016), and tasks
   - Include a seed data task when schemas are introduced/modified
3. After all artifacts are created, verify with `openspec status --change "<change-name>" --json`

## Phase 2: Plan to Issues

1. Parse tasks.md into plan.json
2. Create a tracking issue: "[OpenSpec] <change-name>" with task checklist
3. Create one issue per task, prefixed with task number
4. Update plan.json with issue numbers
5. Update the original change issue (#<issue-number>) with a link to the tracking issue

## Phase 3: Implement (Apply)

1. Read all context files (proposal, specs, design, tasks)
2. For each task in order:
   - Implement the code changes
   - Write PHPUnit tests for new PHP services (3+ test methods each)
   - Write Vue component tests if applicable
   - Update documentation (README.md or docs/)
   - Mark task as [x] in tasks.md
   - Update GitHub issue checkboxes and close task issues
   - Update tracking issue checklist
   - Commit after each task: "feat(<app>): <task-title> [#<issue>]"
3. After all tasks: run quality checks
   - PHP: `composer check:strict` (or phpcs + phpmd + psalm individually)
   - Frontend: `npm run lint` + `npm run stylelint`
   - Fix any failures (up to 3 cycles)

## Phase 4: Verify

1. Check task completion (all [x] in tasks.md)
2. Verify spec coverage (requirements → code mapping)
3. Check design adherence
4. Verify test coverage (every new service has tests)
5. Fix any CRITICAL or WARNING issues found
6. Re-verify after fixes

## Phase 5: Archive

1. Sync delta specs to main specs if they exist
2. Move change to archive: openspec/changes/archive/YYYY-MM-DD-<change-name>
3. Update feature docs (docs/features/README.md and individual feature file)
4. Close tracking issue with archive comment

## Phase 6: Push and report

1. Push the branch:
   ```bash
   cd /tmp/worktrees/<app>-<change-name>
   git push origin feature/<issue-number>/<change-name>
   ```
2. Report back with:
   - Total tasks completed
   - Quality check results
   - Verification status
   - Branch name ready for PR
   - Any issues encountered

Do NOT create the PR — the main agent handles that after reviewing the results.
Do NOT add Co-Authored-By trailers to commit messages.
```

**Agent configuration:**
- Use `isolation: "worktree"` if the agent supports it, OR pre-create worktrees in Step 3
- Use `run_in_background: true` for all agents
- Assign browser numbers (browser-2 through browser-5, browser-7) if agents need browser access

### 5. Monitor progress

While agents are running:
- Track which agents have completed
- As each agent completes, capture its result summary
- If an agent fails, log the error and continue with others
- Launch queued agents as slots become available

Display progress updates:

```
## Pipeline Progress

| # | App | Change | Status | Tasks | Quality |
|---|-----|--------|--------|-------|---------|
| 1 | procest | brp-kvk-register-sets | ✓ Complete | 7/7 | All pass |
| 2 | pipelinq | sla-tracking | ⏳ Running | 3/5 | — |
| 3 | pipelinq | routing | ⏳ Queued | — | — |
```

### 6. Create Pull Requests

For each successfully completed change, create a PR from the feature branch to `development`:

```bash
gh pr create \
  --repo <owner/repo> \
  --base development \
  --head feature/<issue-number>/<change-name> \
  --title "feat(<app>): <Change Title>" \
  --body "$(cat <<'EOF'
## Summary
<1-3 bullet points from proposal.md>

## OpenSpec Change
- **Change:** <change-name>
- **Tracking issue:** #<tracking-issue>
- **Tasks:** N/N complete

## Quality Checks
| Check | Status |
|-------|--------|
| PHPCS | ✓ |
| PHPMD | ✓ |
| Psalm | ✓ |
| Tests | ✓ N tests |

## Standards
<standards from proposal.md>

Closes #<original-issue-number>

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Update the original issue with a link to the PR.

### 7. Clean up worktrees

After all PRs are created:
```bash
# For each completed change
cd <app-directory>
git worktree remove /tmp/worktrees/<app>-<change-name>
```

### 8. Final report

Display the complete pipeline results:

```
## Pipeline Complete

### Results
| # | App | Change | Branch | PR | Tasks | Quality | Status |
|---|-----|--------|--------|-----|-------|---------|--------|
| 1 | procest | brp-kvk-register-sets | feature/103/brp-kvk-register-sets | #105 | 7/7 | ✓ | Merged-ready |
| 2 | pipelinq | sla-tracking | feature/79/sla-tracking | #82 | 5/5 | ✓ | Merged-ready |
| ... |

### Summary
- Changes processed: N
- Successful: N
- Failed: N (with reasons)
- PRs created: N
- Total tasks implemented: N
- Total tests written: N

### Failed Changes (if any)
- <change-name>: <reason for failure>
  Worktree preserved at: /tmp/worktrees/<app>-<change-name>
  To resume: fix the issue and run `/opsx:pipeline <change-name>`
```

---

## Guardrails

- **Worktree isolation**: Each change works in `/tmp/worktrees/<app>-<change-name>` — NEVER modify the main working directory from a subagent
- **Branch naming**: Always `feature/<issue-number>/<change-name>` based off `origin/development`
- **No destructive git ops**: No force push, no reset, no clean, no rebase on shared branches
- **Max parallelism**: 5 concurrent agents (limited by browser pool and system resources)
- **Autonomous operation**: Subagents resolve issues themselves. Only escalate to user if fundamentally blocked (e.g., missing dependency, ambiguous requirement with no reasonable default)
- **Quality gates**: Every change must pass quality checks before PR. If checks fail after 3 fix cycles, mark as failed and preserve worktree for manual intervention
- **Issue hygiene**: Every change gets issues, every task updates its issue, every PR references its issues
- **No Co-Authored-By**: Commit messages must NOT include Co-Authored-By trailers
- **Commit per task**: Each implemented task gets its own commit with a descriptive message
- **PR to development**: Always target `development` branch, never `main` or `beta`

## Error Handling

- **Agent timeout**: If an agent runs for more than 30 minutes with no progress, consider it stuck. Preserve worktree and report.
- **Quality check failures**: Agent fixes up to 3 cycles. After that, mark as failed with details.
- **Git conflicts**: If worktree creation fails due to branch conflicts, create from a fresh development checkout.
- **Missing openspec CLI**: If `openspec` command is not available, fall back to manual artifact creation (read proposal, create specs/design/tasks manually following the templates).
- **Org-wide changes (.github)**: These don't follow the same app structure. Skip ff/apply for these — they are documentation/compliance changes that need manual implementation per app.
