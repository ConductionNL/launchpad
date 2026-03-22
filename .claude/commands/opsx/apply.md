---
name: "OPSX: Apply"
description: Implement tasks from an OpenSpec change (Experimental)
category: Workflow
tags: [workflow, artifacts, experimental]
---

Implement tasks from an OpenSpec change.

**Input**: Optionally specify a change name (e.g., `/opsx:apply add-auth`). If omitted, check if it can be inferred from conversation context. If vague or ambiguous you MUST prompt for available changes.

**Steps**

1. **Select the change**

   If a name is provided, use it. Otherwise:
   - Infer from conversation context if the user mentioned a change
   - Auto-select if only one active change exists
   - If ambiguous, run `openspec list --json` to get available changes and use the **AskUserQuestion tool** to let the user select

   Always announce: "Using change: <name>" and how to override (e.g., `/opsx:apply <other>`).

2. **Check status to understand the schema**
   ```bash
   openspec status --change "<name>" --json
   ```
   Parse the JSON to understand:
   - `schemaName`: The workflow being used (e.g., "spec-driven")
   - Which artifact contains the tasks (typically "tasks" for spec-driven, check status for others)

3. **Get apply instructions**

   ```bash
   openspec instructions apply --change "<name>" --json
   ```

   This returns:
   - Context file paths (varies by schema)
   - Progress (total, complete, remaining)
   - Task list with status
   - Dynamic instruction based on current state

   **Handle states:**
   - If `state: "blocked"` (missing artifacts): show message, suggest using `/opsx:continue`
   - If `state: "all_done"`: congratulate, suggest archive
   - Otherwise: proceed to implementation

4. **Read context files**

   Read the files listed in `contextFiles` from the apply instructions output.
   The files depend on the schema being used:
   - **spec-driven**: proposal, specs, design, tasks
   - Other schemas: follow the contextFiles from CLI output

5. **Show current progress and confirm**

   Display:
   - Schema being used
   - Progress: "N/M tasks complete"
   - Remaining tasks overview
   - Dynamic instruction from CLI

   Then use **AskUserQuestion** to ask:

   > "Ready to implement <N> remaining tasks for `<change-name>`?"

   Options:
   - **Start implementing** — proceed through all pending tasks in order
   - **Show me the full task list first** — display all tasks with titles and acceptance criteria, then ask again
   - **Start from a specific task** — ask "Which task number?" and skip to that task
   - **Cancel** — end the session without making changes

6. **Implement tasks (loop until done or blocked)**

   For each pending task:
   - Show which task is being worked on
   - Make the code changes required
   - Keep changes minimal and focused
   - **Write tests for every new PHP service/controller** — PHPUnit test in `tests/Unit/` or `tests/unit/` with at least 3 test methods covering the happy path, error handling, and edge cases
   - **Write tests for every new Vue component** — if the project has a test framework (Jest/Vitest), create a basic mount + render test
   - **Update documentation** — add/update the feature description in the project's README.md or docs/ folder. At minimum, document new API endpoints (method, path, request/response) and new admin settings
   - Mark task complete in the tasks file: `- [ ]` → `- [x]`
   - **Update GitHub issue checkboxes and close** (if `plan.json` exists):
     - Read `openspec/changes/<name>/plan.json`, find the task's `github_issue` and `tracking_issue` numbers
     1. **Check off acceptance criteria in task issue body**:
        - **MCP (preferred):** `get_issue` → `{owner, repo, issue_number: <task_issue>}` → in the body replace each `- [ ] <criterion>` with `- [x] <criterion>` → `update_issue` → `{owner, repo, issue_number: <task_issue>, body: <updated_body>}`
        - **CLI (fallback):** `gh issue view <task_issue> --repo <repo> --json body --jq '.body'` → replace checkbox lines → `gh issue edit <task_issue> --repo <repo> --body "<updated_body>"`
     2. **Check off this task in the tracking issue body**:
        - **MCP (preferred):** `get_issue` → `{owner, repo, issue_number: <tracking_issue>}` → find the line referencing this task (by title or `#<task_issue>`) → change `- [ ]` to `- [x]` → `update_issue` → `{owner, repo, issue_number: <tracking_issue>, body: <updated_body>}`
        - **CLI (fallback):** `gh issue view <tracking_issue> --repo <repo> --json body --jq '.body'` → update the task's checkbox line → `gh issue edit <tracking_issue> --repo <repo> --body "<updated_body>"`
     3. **Close task issue with comment**:
        - **MCP (preferred):** `update_issue` → `{owner, repo, issue_number: <task_issue>, state: "closed"}`, then `add_issue_comment` → `{owner, repo, issue_number: <task_issue>, body: "✓ Implemented"}`
        - **CLI (fallback):** `gh issue close <task_issue> --repo <repo> --comment "✓ Implemented"`
     - Update `plan.json`: set `"status": "done"` for that task
   - Continue to next task

   **Pause if:**
   - Task is unclear → ask for clarification
   - Implementation reveals a design issue → suggest updating artifacts
   - Error or blocker encountered → report and wait for guidance
   - User interrupts

7. **On completion or pause, show status**

   Display:
   - Tasks completed this session
   - Overall progress: "N/M tasks complete"
   - If paused: explain why and wait for guidance

   **If all tasks done:** proceed to Step 8 (quality checks).

   **If plan.json exists and all tasks are now done:** also close the tracking issue:
   - **MCP (preferred):** GitHub MCP `update_issue` → `{owner, repo, issue_number: <tracking_issue>, state: "closed"}`, then `add_issue_comment` → `{owner, repo, issue_number: <tracking_issue>, body: "✓ All tasks implemented. Running quality checks."}`
   - **CLI (fallback):** `gh issue close <tracking_issue> --repo <repo> --comment "✓ All tasks implemented. Running quality checks."`

8. **Run code quality checks**

   After all tasks are complete, run the full quality suite from the project directory.

   **PHP quality** (if the project has a `composer.json` with quality scripts):
   ```bash
   cd <project-dir> && composer check:strict 2>&1
   ```
   This runs: lint + named-args check + phpcs + phpmd + psalm + phpstan + unit tests.

   If `check:strict` is not available, fall back to running individually:
   ```bash
   composer phpcs 2>&1
   composer phpmd 2>&1
   composer psalm 2>&1
   ```

   **Frontend quality** (if the project has a `package.json` with lint scripts):
   ```bash
   cd <project-dir> && npm run lint 2>&1
   npm run stylelint 2>&1
   ```

   **Handle failures:**
   - Parse the output to identify specific errors
   - For auto-fixable issues, run the fixer first:
     - `composer phpcs:fix` (PHPCBF auto-fixes ~60% of PHPCS issues)
     - `npm run lint -- --fix` (ESLint auto-fix)
   - For remaining issues, fix them manually in the code
   - Re-run the quality checks to confirm all issues are resolved
   - Maximum 3 fix cycles — if issues persist after 3 rounds, report remaining issues and continue

   **Show quality results:**
   ```
   ## Quality Checks

   | Tool | Status |
   |------|--------|
   | PHPCS | ✓ Pass (or X errors fixed) |
   | PHPMD | ✓ Pass |
   | Psalm | ✓ Pass |
   | PHPStan | ✓ Pass |
   | ESLint | ✓ Pass |
   | Stylelint | ✓ Pass |
   | Unit Tests | ✓ Pass (N tests) |
   ```

9. **Ask what's next**

   After quality checks pass (or remaining issues are reported), show the completion summary, then use **AskUserQuestion** to ask:

   > "Implementation complete! What would you like to do next?"

   Options:
   - **Verify implementation** (`/opsx:verify`) — recommended: check the code matches specs and run API/browser tests
   - **Get a code review first** (`/opsx:team-reviewer`) — have a reviewer look at the code before verifying
   - **Sync delta specs** (`/opsx:sync`) — sync this change's specs to main specs first
   - **Archive directly** (`/opsx:archive`) — skip verification if already reviewed externally
   - **Done for now** — end the session

**Output During Implementation**

```
## Implementing: <change-name> (schema: <schema-name>)

Working on task 3/7: <task description>
[...implementation happening...]
✓ Task complete

Working on task 4/7: <task description>
[...implementation happening...]
✓ Task complete
```

**Output On Completion**

```
## Implementation Complete

**Change:** <change-name>
**Schema:** <schema-name>
**Progress:** 7/7 tasks complete ✓

### Completed This Session
- [x] Task 1
- [x] Task 2
...

### Quality Checks
| Tool | Status |
|------|--------|
| PHPCS | ✓ Pass |
| PHPMD | ✓ Pass |
| Psalm | ✓ Pass |
| ESLint | ✓ Pass |
| Unit Tests | ✓ 42 tests passed |

**What's Next**
Recommended: `/opsx:verify` | Optional: `/opsx:team-reviewer`, `/opsx:sync` | Alternative: `/opsx:archive`
```

**Output On Pause (Issue Encountered)**

```
## Implementation Paused

**Change:** <change-name>
**Schema:** <schema-name>
**Progress:** 4/7 tasks complete

### Issue Encountered
<description of the issue>

**Options:**
1. <option 1>
2. <option 2>
3. Other approach

What would you like to do?
```

**Guardrails**
- Keep going through tasks until done or blocked
- Always read context files before starting (from the apply instructions output)
- If task is ambiguous, pause and ask before implementing
- If implementation reveals issues, pause and suggest artifact updates
- Keep code changes minimal and scoped to each task
- Update task checkbox immediately after completing each task
- Pause on errors, blockers, or unclear requirements - don't guess
- Use contextFiles from CLI output, don't assume specific file names

**Fluid Workflow Integration**

This skill supports the "actions on a change" model:

- **Can be invoked anytime**: Before all artifacts are done (if tasks exist), after partial implementation, interleaved with other actions
- **Allows artifact updates**: If implementation reveals design issues, suggest updating artifacts - not phase-locked, work fluidly
