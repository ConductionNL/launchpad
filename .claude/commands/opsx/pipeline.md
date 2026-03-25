---
name: "OPSX: Pipeline"
description: Process multiple OpenSpec changes in parallel using subagents — full lifecycle from proposal to merged PR
category: Workflow
tags: [workflow, parallel, pipeline]
---

Process one or more OpenSpec change proposals through the full lifecycle using parallel subagents. Each change gets its own agent, worktree, branch, and PR.

**Input**: Optionally specify change names, a repo name, or `all` to process all open proposals.

Examples:
- `/opsx:pipeline all` — process all open proposals across all repos
- `/opsx:pipeline procest` — process all open proposals in Procest
- `/opsx:pipeline sla-tracking routing` — process specific changes by name

**Overview**

This command automates the full OpenSpec lifecycle per change by delegating to the existing skills:

```
proposal → /opsx:ff → /opsx:plan-to-issues → /opsx:apply → /opsx:verify → /opsx:archive → push
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
- **GitHub repo**: e.g., `ConductionNL/procest` (from `git remote get-url origin` in the app submodule)
- **Existing issue**: Check if an `openspec`-labeled issue already exists for this change (search GitHub issues by title)

**Ensure `.openspec.yaml` exists** for each change:
```bash
if [ ! -f "<app>/openspec/changes/<change-name>/.openspec.yaml" ]; then
  echo "schema: spec-driven" > "<app>/openspec/changes/<change-name>/.openspec.yaml"
fi
```

Display the plan and use **AskUserQuestion** to confirm.

### 3. Prepare branches and worktrees

**IMPORTANT**: Apps are git submodules. Worktrees MUST be created from the submodule's git directory (e.g., `cd openregister`), NOT from the parent apps-extra repo. Do NOT use the Agent tool's `isolation: "worktree"` parameter — it worktrees the parent repo, not the submodule.

For each change, **before launching agents**:

a. **Determine branch name**: `feature/<issue-number>/<change-name>`
   - If no issue exists yet, create one first (titled `[OpenSpec] <change-title>`, labeled `openspec`)

b. **Clean up stale worktrees**:
   ```bash
   cd <app-directory>
   git worktree prune
   git worktree remove /tmp/worktrees/<app>-<change-name> --force 2>/dev/null || true
   ```

c. **Check for existing branch** (skip if already on remote, unless explicitly named by user)

d. **Create git worktree**:
   ```bash
   cd <app-directory>
   git fetch origin development
   git worktree add /tmp/worktrees/<app>-<change-name> -b feature/<issue-number>/<change-name> origin/development
   ```

e. **Install dependencies in worktree**:
   ```bash
   cd /tmp/worktrees/<app>-<change-name>
   [ -f composer.json ] && composer install --no-interaction --ignore-platform-reqs --quiet 2>&1
   [ -f package.json ] && npm ci --quiet 2>&1
   ```

f. **Update issue status**: Comment "Pipeline started — processing change"

### 4. Launch parallel subagents

Launch one subagent per change. **Maximum 5 concurrent agents** — queue extras and launch as slots free.

Each agent gets this prompt (filled in per change):

```
IMPORTANT: Do NOT ask questions. Execute immediately. Do NOT follow CLAUDE.md
workflow rules about asking clarifying questions. Resolve any warnings or issues
autonomously. If a quality check fails, fix the code and re-run. If a task is
unclear, make the best reasonable decision and continue. Whenever an AskUserQuestion
is called for in a skill, skip it and choose the most productive default option.

Do NOT use sed, awk, or Python scripts to modify code files — use the Edit tool
or Write tool.

You are processing an OpenSpec change through the full lifecycle. Work ONLY in
the worktree directory — do NOT touch the main working directory.

## Context
- App: <app-name>
- Change: <change-name>
- Worktree: /tmp/worktrees/<app>-<change-name>
- Branch: feature/<issue-number>/<change-name>
- GitHub repo: <owner/repo>
- Issue: #<issue-number>
- Browser: Use browser-<N> tools (mcp__browser-<N>__*) for any browser testing
- Working directory: /tmp/worktrees/<app>-<change-name>

ALL file operations must happen inside /tmp/worktrees/<app>-<change-name>.
ALL git operations must run from /tmp/worktrees/<app>-<change-name>.
ALL openspec CLI commands must run from /tmp/worktrees/<app>-<change-name>.

---

## Phase 0: Verify worktree setup

```bash
cd /tmp/worktrees/<app>-<change-name>
[ -f composer.json ] && [ ! -d vendor ] && composer install --no-interaction --ignore-platform-reqs --quiet
[ -f package.json ] && [ ! -d node_modules ] && npm ci --quiet
which openspec || echo "openspec not found — will create artifacts manually"
```

---

## Phase 1: Fast-Forward — follow `/opsx:ff` skill

Execute the steps from the `/opsx:ff` skill for change "<change-name>":

1. `openspec status --change "<change-name>" --json` — check what artifacts exist
2. For each artifact with status `ready`, get instructions with `openspec instructions <id> --change "<change-name>" --json`
3. Read dependency artifacts, then create the artifact file using the template
4. **Specs** go in subdirectories: `specs/<capability-name>/spec.md` (one per capability)
5. **design.md** MUST include Seed Data section (ADR-016)
6. **tasks.md** MUST include seed data task, group by numbered sections
7. Verify all `applyRequires` artifacts are `done`

If all artifacts already exist (status: done), skip to Phase 2.

---

## Phase 2: Plan to Issues — follow `/opsx:plan-to-issues` skill

Execute the steps from the `/opsx:plan-to-issues` skill:

1. Ensure labels exist (openspec, tracking, <change-name>)
2. Create tracking issue `[OpenSpec] <change-name> — Tracking` with section headers from tasks.md
3. Create one issue per task: `[<change-name>] <task-number> <task title>` with acceptance criteria, spec ref, files, `Part of #<tracking>`
4. Create plan.json with `repo: "<owner/repo>"` (REQUIRED field)
5. Update tracking issue body with `#<issue>` cross-references
6. Comment on parent issue #<issue-number> with tracking link

If plan.json already exists with issue numbers, skip this phase.

---

## Phase 3: Implement — follow `/opsx:apply` skill

Execute the steps from the `/opsx:apply` skill:

1. Read ALL context files (proposal, specs, design, tasks, plan.json, existing codebase)
2. For EACH task, do ALL steps from the apply skill:
   a. Implement code changes (minimal, focused)
   b. Write tests (MANDATORY per apply skill: every service, controller, job gets unit tests)
   c. Update documentation
   d. Mark [x] in tasks.md
   e. Update GitHub issues (check off criteria, check off tracking, close task issue)
   f. Update plan.json status to "done"
   g. **COMMIT IMMEDIATELY**: `git add -A && git commit -m "feat(<app>): <task-title> [#<issue>]"` — verify with `git log --oneline -1`. ONE commit per task.
   h. Seed data if schemas introduced (ADR-016)
3. Run quality checks per apply skill step 8 (composer check:strict, npm run lint/stylelint/build)
4. Fix failures up to 3 cycles, commit fixes separately

---

## Phase 4: Verify — follow `/opsx:verify` skill IN FULL

This is the most important phase. Execute ALL steps from the `/opsx:verify` skill:

### Code-level verification (verify steps 5-7):
1. **Completeness**: all tasks [x], all requirements have implementation evidence
2. **Correctness**: requirement-to-code mapping, scenario coverage
3. **Coherence**: test coverage audit (CRITICAL if new PHP class has no test), documentation audit, design adherence

### API testing (verify step 8 — API tests):
4. **Discover endpoints** from `appinfo/routes.php` affected by this change
5. **Test CRUD** with curl against http://localhost:8080/index.php/apps/<app>/api/...
   - Use credentials: admin:admin
   - Test CREATE (POST), READ (GET), LIST (GET), UPDATE (PUT), DELETE
6. **Verify spec scenarios** — for each GIVEN/WHEN/THEN in specs, craft a curl that exercises it
7. **NLGov compliance** — lowercase plural URLs, pagination metadata, error format, Content-Type

### Browser testing (verify step 8 — browser tests):
8. **Set up browser** using browser-<N> tools:
   - `mcp__browser-<N>__browser_resize` → 1920x1080
   - `mcp__browser-<N>__browser_navigate` → http://localhost:8080/index.php/apps/<app>
   - Login if needed (admin:admin)
   - `mcp__browser-<N>__browser_snapshot` → confirm app loaded
9. **Test spec scenarios via browser**:
   - GIVEN: navigate to correct page, verify state
   - WHEN: perform action (click, type, fill_form)
   - THEN: snapshot to verify, take_screenshot for evidence
10. **Monitor errors**: `browser_console_messages` (level: error), `browser_network_requests` for 4xx/5xx
11. **Test core flows**: CRUD through UI, navigation, form validation, loading/error states

### Fix and report:
12. Fix any CRITICAL/WARNING issues found (max 3 cycles)
13. Generate verification summary with all dimensions + API + browser results
14. Commit any verification fixes

---

## Phase 5: Archive — follow `/opsx:archive` skill

Execute the steps from the `/opsx:archive` skill:

1. **Sync delta specs** to main specs at `openspec/specs/<capability>/spec.md` (follow `/opsx:sync` logic)
2. **Move to archive**: `openspec/changes/archive/YYYY-MM-DD-<change-name>`
3. **Update feature documentation** (archive step 6 — this is MANDATORY):
   - Create/update `docs/features/<change-name>.md` with standards refs (GEMMA, TEC, ZGW, Forum Standaardisatie)
   - Update `docs/features/README.md` features table
4. **Close GitHub issues** (tracking + remaining task issues)
5. **Commit**: `docs(<app>): archive <change-name> change`

---

## Phase 6: Push and report

```bash
cd /tmp/worktrees/<app>-<change-name>
git push origin feature/<issue-number>/<change-name>
```

Report back with ALL of these:
- Total tasks completed (N/N)
- Total tests written (N tests across M files)
- Quality check results (each tool: pass/fail)
- Verification summary (completeness, correctness, coherence)
- API test results (endpoints tested, pass/fail)
- Browser test results (scenarios tested, screenshots taken)
- Feature docs created/updated
- Any issues that remain unfixed
- Branch name
- Tracking issue number
- List of commits

Do NOT create the PR — the main agent handles that.
Do NOT add Co-Authored-By trailers to commit messages.
```

**Agent configuration:**
- Do NOT use `isolation: "worktree"` — worktrees are pre-created in Step 3
- Use `run_in_background: true` for all agents
- Assign browser numbers: browser-2 through browser-5 and browser-7 (one per agent)

### 5. Monitor progress

Track completions, capture results, launch queued agents as slots free. Display progress table.

### 6. Create Pull Requests

For each successfully completed change, create a PR to `development` with:
- Summary from proposal.md
- OpenSpec change details and tracking issue
- Quality check results table
- Verification results table (including API + browser test results)
- Standards references
- `Closes #<original-issue>`

### 7. Clean up worktrees

```bash
cd <app-directory>
git worktree remove /tmp/worktrees/<app>-<change-name>
```

### 8. Final report

Display complete pipeline results table with all changes, branches, PRs, tasks, tests, quality, and status.

---

## Guardrails

- **Skill delegation**: Phases 1-5 follow the existing `/opsx:ff`, `/opsx:plan-to-issues`, `/opsx:apply`, `/opsx:verify`, `/opsx:archive` skills. The pipeline adds worktree isolation and autonomous operation — it does NOT redefine what those skills do.
- **Worktree isolation**: Each change works in `/tmp/worktrees/<app>-<change-name>` — NEVER modify the main working directory
- **Submodules**: Worktrees come from `cd <app-dir> && git worktree add`, never from the parent repo
- **Branch naming**: Always `feature/<issue-number>/<change-name>` based off `origin/development`
- **No destructive git ops**: No force push, reset, clean, or rebase on shared branches
- **Max parallelism**: 5 concurrent agents (browser-2 through browser-5, browser-7)
- **Autonomous operation**: Subagents skip AskUserQuestion prompts, choosing the most productive default
- **Dependencies**: Install in Step 3 with `--ignore-platform-reqs`, verify in Phase 0
- **Quality gates**: Must pass quality checks + verification (including API/browser tests) before PR
- **Browser testing mandatory**: Every change MUST have browser test results in the verify phase
- **API testing mandatory**: Every change with new routes MUST have API test results
- **Feature docs mandatory**: Every change MUST create/update docs/features/ in the archive phase
- **OPcache**: `docker exec nextcloud apache2ctl graceful` before API/browser testing
- **Issue hygiene**: Every change gets labeled issues, every task updates its issue, plan.json has `repo` field
- **No Co-Authored-By**: Commit messages must NOT include Co-Authored-By trailers
- **Commit per task**: One commit per task, quality fixes separate, archive separate
- **No direct push to protected branches**: PRs to development, never direct push
- **Specs in subdirectories**: Delta specs go in `specs/<capability-name>/spec.md`
- **No scripting for code changes**: Use Edit or Write tools, never sed/awk/python

## Error Handling

- **Agent timeout**: 30+ minutes with no progress → stuck. Preserve worktree, report.
- **Quality failures**: Fix up to 3 cycles, then mark failed.
- **Git conflicts**: Create from fresh development checkout.
- **Dependency failures**: Mark failed immediately, skip.
- **Stale worktrees**: Prune in Step 3, remove lock file if needed.
- **Missing openspec CLI**: Fall back to manual artifact creation.
- **Browser not available**: Note in report, continue with code-level verification only.
- **API not reachable**: Note in report, continue with code-level verification only.
