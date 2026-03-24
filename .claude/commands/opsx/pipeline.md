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
proposal → ff (specs/design/tasks) → plan-to-issues → apply → verify → archive → push → PR
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

**IMPORTANT**: Apps are git submodules. Worktrees MUST be created from the submodule's git directory (e.g., `cd openregister`), NOT from the parent apps-extra repo. Do NOT use the Agent tool's `isolation: "worktree"` parameter — it worktrees the parent repo, not the submodule.

For each change, **before launching agents**:

a. **Determine branch name**: `feature/<issue-number>/<change-name>`
   - If no issue exists yet, create one first (titled `[OpenSpec] <change-title>`, labeled `openspec`)
   - Example: `feature/103/brp-kvk-register-sets`

b. **Clean up stale worktrees** (if any exist for this app):
   ```bash
   cd <app-directory>
   git worktree prune
   ```
   If a worktree already exists at `/tmp/worktrees/<app>-<change-name>`, remove it first:
   ```bash
   git worktree remove /tmp/worktrees/<app>-<change-name> --force 2>/dev/null || true
   ```

c. **Check for existing branch** (duplicate detection for re-runs):
   ```bash
   cd <app-directory>
   git fetch origin
   if git rev-parse --verify origin/feature/<issue-number>/<change-name> >/dev/null 2>&1; then
     echo "Branch already exists on remote — skipping this change"
   fi
   ```
   If the branch already exists on the remote, **skip this change** and note it in the plan as "Already processed". Only re-process if the user explicitly includes it by name.

d. **Create git worktree**:
   ```bash
   cd <app-directory>
   git fetch origin development
   git worktree add /tmp/worktrees/<app>-<change-name> -b feature/<issue-number>/<change-name> origin/development
   ```

e. **Install dependencies in worktree**:
   ```bash
   cd /tmp/worktrees/<app>-<change-name>
   # PHP dependencies (--ignore-platform-reqs because host may lack extensions like ext-soap)
   if [ -f composer.json ]; then
     composer install --no-interaction --ignore-platform-reqs --quiet 2>&1
   fi
   # Frontend dependencies
   if [ -f package.json ]; then
     npm ci --quiet 2>&1
   fi
   ```
   If dependency install fails, mark this change as failed and skip it.

f. **Update issue status**: Add a comment "Pipeline started — processing change"

### 4. Launch parallel subagents

Launch one subagent per change. **Maximum 5 concurrent agents** — if more changes exist, queue them and launch new agents as earlier ones complete.

Each agent gets this prompt (filled in per change):

```
IMPORTANT: Do NOT ask questions. Execute immediately. Do NOT follow CLAUDE.md
workflow rules about asking clarifying questions. Resolve any warnings or issues
autonomously. If a quality check fails, fix the code and re-run. If a task is
unclear, make the best reasonable decision and continue.

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
- Working directory: /tmp/worktrees/<app>-<change-name>

ALL file operations must happen inside /tmp/worktrees/<app>-<change-name>.
ALL git operations must run from /tmp/worktrees/<app>-<change-name>.
ALL openspec CLI commands must run from /tmp/worktrees/<app>-<change-name>.

---

## Phase 0: Verify worktree setup

```bash
cd /tmp/worktrees/<app>-<change-name>
```

Verify dependencies are installed (they should be from Step 3, but confirm):
```bash
if [ -f composer.json ] && [ ! -d vendor ]; then
  composer install --no-interaction --ignore-platform-reqs --quiet
fi
if [ -f package.json ] && [ ! -d node_modules ]; then
  npm ci --quiet
fi
```

Verify the openspec CLI is available:
```bash
which openspec || echo "openspec not found — will create artifacts manually"
```

---

## Phase 1: Fast-Forward (generate all artifacts)

### 1.1 Check current status
```bash
cd /tmp/worktrees/<app>-<change-name>
openspec status --change "<change-name>" --json
```

### 1.2 Generate artifacts in dependency order

Loop through artifacts until all `applyRequires` artifacts have status `done`:

For each artifact with status `ready`:

a. **Get instructions**:
   ```bash
   openspec instructions <artifact-id> --change "<change-name>" --json
   ```
   This returns: `template`, `instruction`, `outputPath`, `context`, `rules`, `dependencies`, `unlocks`.

b. **Read dependency artifacts** listed in `dependencies` for context.

c. **Create the artifact file** at `outputPath` using `template` as the structure.
   Apply `context` and `rules` as constraints but do NOT copy them into the file.

d. **Artifact-specific requirements**:

   **For specs** (`outputPath: "specs/**/*.md"`):
   - Create ONE spec file per capability listed in the proposal's Capabilities section
   - Each spec goes in its own subdirectory: `openspec/changes/<change-name>/specs/<capability-name>/spec.md`
   - Use kebab-case for capability names (e.g., `knowledge-base`, `article-management`)
   - Each spec must have: Purpose, Requirements (with `### Requirement:` headers), Scenarios (with `#### Scenario:` and GIVEN/WHEN/THEN)

   **For design.md**:
   - MUST include a **Seed Data** section (ADR-016) when schemas are introduced/modified
   - Research realistic objects per schema with general organization data

   **For tasks.md**:
   - MUST include a seed data task when schemas are introduced/modified
   - Group tasks into numbered sections (e.g., `## 1. Backend`, `## 2. Frontend`)
   - Each task: `- [ ] <number> <title>` with acceptance_criteria, spec_ref, and files fields

e. **Verify** after each artifact: re-run `openspec status --change "<change-name>" --json` and check status.

### 1.3 Final verification
```bash
openspec status --change "<change-name>" --json
```
ALL `applyRequires` artifacts must have `status: "done"`. If not, fix and retry.

---

## Phase 2: Plan to Issues

### 2.1 Ensure GitHub labels exist
```bash
gh label list --repo <owner/repo> --json name --jq '.[].name' | grep -q "openspec" || \
  gh label create "openspec" --repo <owner/repo> --color "0075ca"
gh label list --repo <owner/repo> --json name --jq '.[].name' | grep -q "tracking" || \
  gh label create "tracking" --repo <owner/repo> --color "e4e669"
gh label list --repo <owner/repo> --json name --jq '.[].name' | grep -q "<change-name>" || \
  gh label create "<change-name>" --repo <owner/repo> --color "d93f0b"
```

### 2.2 Create tracking issue (epic)
The tracking issue body MUST preserve section headers from tasks.md as `### N. Section Name` headings above grouped task checkboxes — not a flat list.

```bash
gh issue create --repo <owner/repo> \
  --title "[OpenSpec] <change-name> — Tracking" \
  --label "openspec,tracking" \
  --body "<summary from proposal.md>

## Tasks

### 1. Section Name
- [ ] 1.1 Task title
- [ ] 1.2 Task title

### 2. Section Name
- [ ] 2.1 Task title
..."
```
Capture the tracking issue number.

### 2.3 Create one issue per task
Each task issue MUST have:
- Title: `[<change-name>] <task-number> <task title>`
- Body with:
  - Task description
  - Acceptance criteria as checkboxes: `- [ ] <criterion>`
  - Spec reference
  - Files likely affected
  - Footer: `---\n*Part of #<tracking_issue>*`
- Labels: `openspec`, `<change-name>`

```bash
gh issue create --repo <owner/repo> \
  --title "[<change-name>] <task-number> <task title>" \
  --label "openspec,<change-name>" \
  --body "<description>

## Acceptance Criteria
- [ ] <criterion 1>
- [ ] <criterion 2>

**Spec ref:** <spec_ref>
**Files:** <files>

---
*Part of #<tracking_issue>*"
```
Capture each task issue number.

### 2.4 Create plan.json
Write to `openspec/changes/<change-name>/plan.json`:
```json
{
  "change": "<change-name>",
  "project": "<app-name>",
  "repo": "<owner/repo>",
  "created": "<ISO-date>",
  "tracking_issue": <tracking-issue-number>,
  "tasks": [
    {
      "id": 1,
      "title": "<task title>",
      "description": "<description>",
      "github_issue": <task-issue-number>,
      "status": "pending",
      "spec_ref": "<spec_ref>",
      "acceptance_criteria": ["<criterion>"],
      "files_likely_affected": ["<file>"],
      "labels": ["openspec", "<change-name>"]
    }
  ]
}
```
The `repo` field is REQUIRED — it must be `<owner/repo>` (e.g., `ConductionNL/pipelinq`).

### 2.5 Update tracking issue with cross-references
Fetch the tracking issue body, replace each `- [ ] <task-number> <title>` with `- [ ] #<task-issue> <task-number> <title>`, then update:
```bash
gh issue view <tracking_issue> --repo <owner/repo> --json body --jq '.body' > /tmp/tracking_body.txt
# Replace task lines with issue references
gh issue edit <tracking_issue> --repo <owner/repo> --body "$(cat /tmp/tracking_body.txt)"
```

### 2.6 Link to original issue
```bash
gh issue comment <issue-number> --repo <owner/repo> \
  --body "Tracking issue: #<tracking_issue>"
```

---

## Phase 3: Implement (Apply)

### 3.1 Read all context files
Read ALL of these before writing any code:
- `openspec/changes/<change-name>/proposal.md`
- `openspec/changes/<change-name>/design.md`
- `openspec/changes/<change-name>/tasks.md`
- `openspec/changes/<change-name>/plan.json`
- All spec files in `openspec/changes/<change-name>/specs/*/spec.md`
- Existing codebase files relevant to the change (routes, services, controllers, views)

### 3.2 Implement each task (one at a time, in order)

For EACH task, do ALL of these steps — do not skip any:

**a. Implement the code changes**
- Keep changes minimal and focused on this task
- Follow existing project patterns

**b. Write tests**
- **Every new PHP service/controller** gets a PHPUnit test in `tests/Unit/` with at least 3 test methods: happy path, error handling, edge case
- **Every new Vue component** gets a test if the project has Jest/Vitest
- Tests must actually run and pass

**c. Update documentation**
- Add/update feature description in README.md or docs/
- Document new API endpoints: method, path, request body, response format
- Document new admin settings or configuration

**d. Mark task complete in tasks.md**
Change `- [ ]` to `- [x]` for this task.

**e. Update GitHub issues** (read plan.json for issue numbers)

1. **Check off acceptance criteria in the task issue body**:
   ```bash
   BODY=$(gh issue view <task_issue> --repo <owner/repo> --json body --jq '.body')
   # Replace all "- [ ]" with "- [x]" for this task's criteria
   UPDATED=$(echo "$BODY" | sed 's/- \[ \]/- [x]/g')
   gh issue edit <task_issue> --repo <owner/repo> --body "$UPDATED"
   ```

2. **Check off this task in the tracking issue body**:
   ```bash
   BODY=$(gh issue view <tracking_issue> --repo <owner/repo> --json body --jq '.body')
   # Find the line with this task's issue number or title, change [ ] to [x]
   gh issue edit <tracking_issue> --repo <owner/repo> --body "$UPDATED_BODY"
   ```

3. **Close the task issue**:
   ```bash
   gh issue close <task_issue> --repo <owner/repo> --comment "Implemented in feature/<issue-number>/<change-name>"
   ```

4. **Update plan.json**: set this task's `"status": "done"`.

**f. Commit this task's changes**
```bash
git add -A
git commit -m "$(cat <<'EOF'
feat(<app>): <task-title> [#<task_issue>]
EOF
)"
```
ONE commit per task. Do not batch multiple tasks into one commit.

**g. Seed data** (if this task introduces/modifies OpenRegister schemas):
- Generate seed data entries in `lib/Settings/<app>_register.json`
- Use the Seed Data section from design.md
- Use `@self` envelope pattern per ADR-013
- 3-5 objects per schema with realistic, varied field values

### 3.3 Quality checks (after ALL tasks are complete)

Run the full quality suite:

```bash
cd /tmp/worktrees/<app>-<change-name>

# PHP quality
if [ -f composer.json ]; then
  composer check:strict 2>&1 || {
    # If check:strict not available, run individually:
    composer phpcs 2>&1
    composer phpmd 2>&1
    composer psalm 2>&1
    php vendor/bin/phpunit 2>&1
  }
fi

# Frontend quality
if [ -f package.json ]; then
  npm run lint 2>&1
  npm run stylelint 2>&1
  npm run build 2>&1
fi
```

**Handle failures:**
1. Run auto-fixers first: `composer phpcs:fix`, `npm run lint -- --fix`
2. Fix remaining issues manually in code
3. Re-run quality checks
4. Maximum 3 fix cycles — if issues persist, note them in report and continue

**After quality checks pass**, clear OPcache:
```bash
docker exec nextcloud apache2ctl graceful 2>/dev/null || true
```

Commit any quality fixes:
```bash
git add -A
git commit -m "fix(<app>): quality check fixes for <change-name>"
```

---

## Phase 4: Verify

Perform a structured verification against the specs. Do NOT skip this phase.

### 4.1 Verify Completeness

**Task completion:**
- Re-read tasks.md — ALL tasks must be `[x]`
- If any are `[ ]`, go back and implement them before continuing

**Spec coverage:**
- Read each spec file in `openspec/changes/<change-name>/specs/*/spec.md`
- Extract all `### Requirement:` blocks
- For each requirement, search the codebase for implementation evidence (grep for keywords, class names, method names)
- If a requirement has no implementation evidence, add to issues list as CRITICAL

### 4.2 Verify Correctness

**Requirement-to-code mapping:**
- For each requirement, note the file paths and line ranges where it's implemented
- Check that the implementation matches the requirement's intent

**Scenario coverage:**
- For each `#### Scenario:` in the specs, verify:
  - The GIVEN/WHEN/THEN conditions are handled in code
  - A test exists covering the scenario (or the scenario is implicitly covered)
- If a scenario is uncovered, add as WARNING

### 4.3 Verify Coherence

**Test coverage audit:**
- List all NEW PHP service/controller files added by this change (git diff)
- For each, verify a corresponding test file exists in `tests/Unit/`
- If a new service has no test, add as CRITICAL

**Documentation audit:**
- Check that README.md or docs/ mentions the new feature
- Check that new API endpoints are documented
- If no docs, add as WARNING

**Design adherence:**
- Read design.md decisions
- Verify implementation follows them
- If contradictions found, add as WARNING

### 4.4 Fix issues

If any CRITICAL or WARNING issues were found:
1. Fix the code/tests/docs
2. Commit fixes: `fix(<app>): verification fixes for <change-name>`
3. Re-verify (only the dimensions that had issues)
4. Maximum 3 fix-verify cycles

### 4.5 Generate verification summary

```
## Verification Report: <change-name>

| Dimension    | Result |
|-------------|--------|
| Completeness | N/N tasks, M/M requirements |
| Correctness  | M/N scenarios covered |
| Coherence    | Tests: OK, Docs: OK, Design: OK |

Issues: X critical, Y warning, Z suggestion
```

---

## Phase 5: Archive

### 5.1 Sync delta specs to main specs

If spec files exist in `openspec/changes/<change-name>/specs/`:
- For each capability spec, read the delta spec
- Read the corresponding main spec at `openspec/specs/<capability>/spec.md` (may not exist)
- Apply changes intelligently:
  - **ADDED Requirements**: Add to main spec
  - **MODIFIED Requirements**: Merge changes, preserve existing scenarios not mentioned
  - **REMOVED Requirements**: Remove from main spec
  - **RENAMED Requirements**: Rename in main spec
- If main spec doesn't exist, create `openspec/specs/<capability>/spec.md`

### 5.2 Move to archive
```bash
mkdir -p openspec/changes/archive
mv openspec/changes/<change-name> openspec/changes/archive/YYYY-MM-DD-<change-name>
```

### 5.3 Update feature documentation

Check if `docs/features/README.md` exists:

**If yes:**
- Read the Spec-to-Feature Mapping to find the matching feature doc
- Update the feature doc with new/changed features from the synced specs
- If no matching feature doc exists, create `docs/features/<change-name>.md` with:
  - Feature title and summary
  - Standards references (GEMMA, TEC RFP section, Forum Standaardisatie, ZGW)
  - Overview and key capabilities from the spec requirements
- Update the Features table in `docs/features/README.md`

**If no:** Create `docs/features/README.md` with:
- App name and description
- Standards Compliance table
- Features table with this feature

### 5.4 Close GitHub tracking issue
```bash
gh issue close <tracking_issue> --repo <owner/repo> \
  --comment "Change archived: openspec/changes/archive/YYYY-MM-DD-<change-name>/"
```

Close any remaining open task issues:
```bash
# For each task in plan.json where github_issue is set
gh issue close <task_issue> --repo <owner/repo> \
  --comment "Archived with change" 2>/dev/null || true
```

### 5.5 Commit archive
```bash
git add -A
git commit -m "docs(<app>): archive <change-name> change"
```

---

## Phase 6: Push and report

### 6.1 Push the branch
```bash
cd /tmp/worktrees/<app>-<change-name>
git push origin feature/<issue-number>/<change-name>
```

### 6.2 Report results
Report back with ALL of these:
- Total tasks completed (N/N)
- Total tests written (N tests across M files)
- Quality check results (PHPCS, PHPMD, Psalm, PHPUnit, ESLint, build — each with pass/fail)
- Verification summary (completeness, correctness, coherence)
- Any issues that remain unfixed
- Branch name: `feature/<issue-number>/<change-name>`
- Tracking issue number
- List of commits on the branch

Do NOT create the PR — the main agent handles that.
Do NOT add Co-Authored-By trailers to commit messages.
```

**Agent configuration:**
- Do NOT use `isolation: "worktree"` from the Agent tool — it worktrees the parent repo, not the submodule. Worktrees are pre-created in Step 3
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
| 1 | procest | brp-kvk-register-sets | Complete | 7/7 | All pass |
| 2 | pipelinq | sla-tracking | Running | 3/5 | — |
| 3 | pipelinq | routing | Queued | — | — |
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
| PHPCS | pass/fail |
| PHPMD | pass/fail |
| Psalm | pass/fail |
| PHPUnit | N tests |
| ESLint | pass/fail |
| Build | pass/fail |

## Verification
| Dimension | Result |
|-----------|--------|
| Completeness | N/N tasks, M/M requirements |
| Correctness | M/N scenarios |
| Test coverage | N new test files |
| Documentation | Updated/Created |

## Standards
<standards from proposal.md>

Closes #<original-issue-number>

Generated with [Claude Code](https://claude.com/claude-code)
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
| # | App | Change | Branch | PR | Tasks | Tests | Quality | Status |
|---|-----|--------|--------|-----|-------|-------|---------|--------|
| 1 | procest | brp-kvk-register-sets | feature/103/brp-kvk-register-sets | #105 | 7/7 | 24 | Pass | Ready |
| 2 | pipelinq | sla-tracking | feature/79/sla-tracking | #82 | 5/5 | 18 | Pass | Ready |
| ... |

### Summary
- Changes processed: N
- Successful: N
- Failed: N (with reasons)
- PRs created: N
- Total tasks implemented: N
- Total tests written: N
- Total commits: N

### Failed Changes (if any)
- <change-name>: <reason for failure>
  Worktree preserved at: /tmp/worktrees/<app>-<change-name>
  To resume: fix the issue and run `/opsx:pipeline <change-name>`
```

---

## Guardrails

- **Worktree isolation**: Each change works in `/tmp/worktrees/<app>-<change-name>` — NEVER modify the main working directory from a subagent
- **Submodules**: Apps are submodules — worktrees come from `cd <app-dir> && git worktree add`, never from the parent repo
- **Branch naming**: Always `feature/<issue-number>/<change-name>` based off `origin/development`
- **No destructive git ops**: No force push, no reset, no clean, no rebase on shared branches
- **Max parallelism**: 5 concurrent agents (limited by browser pool and system resources)
- **Autonomous operation**: Subagents resolve issues themselves — do not ask questions. Make the best reasonable decision and continue.
- **Dependencies**: Worktrees need their own `vendor/` and `node_modules/` — install in Step 3 with `--ignore-platform-reqs`, verify in Phase 0
- **Quality gates**: Every change must pass quality checks (including `npm run build`) before PR. If checks fail after 3 fix cycles, mark as failed and preserve worktree
- **OPcache**: Run `docker exec nextcloud apache2ctl graceful` after code changes before API testing
- **Issue hygiene**: Every change gets labeled issues, every task updates its issue checkboxes, every PR references its issues, plan.json has `repo` field
- **No Co-Authored-By**: Commit messages must NOT include Co-Authored-By trailers
- **Commit per task**: Each implemented task gets its own commit. Quality fixes get a separate commit. Archive gets a separate commit.
- **PR to development**: Always target `development` branch, never `main` or `beta`
- **Specs in subdirectories**: Delta specs go in `specs/<capability-name>/spec.md`, not flat `specs.md`
- **No scripting for code changes**: Do NOT use sed, awk, or Python scripts to modify code — use Edit or Write tools
- **Every phase mandatory**: Do not skip Phase 4 (Verify) or Phase 5.3 (feature docs). Every step in every phase must be completed.

## Error Handling

- **Agent timeout**: If an agent runs for more than 30 minutes with no progress, consider it stuck. Preserve worktree and report.
- **Quality check failures**: Agent fixes up to 3 cycles. After that, mark as failed with details.
- **Git conflicts**: If worktree creation fails due to branch conflicts, create from a fresh development checkout.
- **Dependency install failures**: If `composer install` or `npm ci` fails in the worktree, mark that change as failed immediately.
- **Stale worktrees**: Step 3 prunes stale worktrees. If `git worktree add` still fails, try removing the lock file and retry once.
- **Missing openspec CLI**: Fall back to manual artifact creation following the templates from `openspec instructions`.
- **Missing .openspec.yaml**: Create it with `schema: spec-driven` before running openspec CLI commands.
- **Org-wide changes (.github)**: Skip ff/apply — these need manual implementation per app.
