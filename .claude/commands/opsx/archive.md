---
name: "OPSX: Archive"
description: Archive a completed change in the experimental workflow
category: Workflow
tags: [workflow, archive, experimental]
---

Archive a completed change in the experimental workflow.

**Input**: Optionally specify a change name after `/opsx:archive` (e.g., `/opsx:archive add-auth`). If omitted, check if it can be inferred from conversation context. If vague or ambiguous you MUST prompt for available changes.

**Steps**

1. **If no change name provided, prompt for selection**

   Run `openspec list --json` to get available changes. Use the **AskUserQuestion tool** to let the user select.

   Show only active changes (not already archived).
   Include the schema used for each change if available.

   **IMPORTANT**: Do NOT guess or auto-select a change. Always let the user choose.

2. **Check artifact completion status**

   Run `openspec status --change "<name>" --json` to check artifact completion.

   Parse the JSON to understand:
   - `schemaName`: The workflow being used
   - `artifacts`: List of artifacts with their status (`done` or other)

   **If any artifacts are not `done`:**
   - Display warning listing incomplete artifacts
   - Prompt user for confirmation to continue
   - Proceed if user confirms

3. **Check task completion status**

   Run two checks in parallel — local tasks file and GitHub tracking issue.

   **A. Local tasks.md check**: Read the tasks file (typically `tasks.md`) and count tasks marked `- [ ]` (incomplete) vs `- [x]` (complete).

   **B. GitHub tracking issue check** (if plan.json exists): Read plan.json to get `tracking_issue` and `repo`, then:
   - **MCP (preferred):** `get_issue` → `{owner, repo, issue_number: <tracking_issue>}` → scan body for `- [ ]` lines
   - **CLI (fallback):** `gh issue view <tracking_issue> --repo <repo> --json body --jq '.body'` → count `- [ ]` lines

   **Reconcile findings** — two distinct failure modes:

   **Case A — Sync gap**: tasks.md shows all done, but the GitHub tracking issue has unchecked boxes. The work is complete but GitHub is out of date.
   - **BLOCK the archive** and display:
     ```
     ⛔ Cannot archive: GitHub tracking issue #<n> has N unchecked task(s) but tasks.md shows all complete.
     This is a sync gap — the tracking issue needs to be updated.
     ```
   - Use **AskUserQuestion** to ask: "How do you want to proceed?"
     - **Fix the tracking issue checkboxes now** — Update the GitHub tracking issue body to match tasks.md (check off all tasks that are `[x]` in tasks.md), then continue to archive
     - **Archive anyway (override)** — Archive without fixing; add a warning note to the archive summary
   - Only proceed if user explicitly chooses one of these options

   **Case B — Genuinely incomplete tasks**: tasks.md has `- [ ]` items (regardless of what GitHub shows). The work is not done.
   - **BLOCK the archive** and display:
     ```
     ⛔ Cannot archive: N task(s) are still incomplete in tasks.md:
     - Task X: <title>
     ```
   - Use **AskUserQuestion** to ask: "How do you want to proceed?"
     - **Go back and complete tasks** — End the session so the user can finish the work
     - **Archive anyway (override)** — Archive despite incomplete tasks; add a warning note to the archive summary
   - Only proceed if user explicitly chooses the override option

   **If no tasks file exists:** Proceed without task-related warning.

4. **Assess delta spec sync state**

   Check for delta specs at `openspec/changes/<name>/specs/`. If none exist, proceed without sync prompt.

   **If delta specs exist:**
   - Compare each delta spec with its corresponding main spec at `openspec/specs/<capability>/spec.md`
   - Determine what changes would be applied (adds, modifications, removals, renames)
   - Show a combined summary before prompting

   **Prompt options:**
   - If changes needed: "Sync now (recommended)", "Archive without syncing"
   - If already synced: "Archive now", "Sync anyway", "Cancel"

   If user chooses sync, execute `/opsx:sync` logic. Proceed to archive regardless of choice.

5. **Perform the archive**

   Create the archive directory if it doesn't exist:
   ```bash
   mkdir -p openspec/changes/archive
   ```

   Generate target name using current date: `YYYY-MM-DD-<change-name>`

   **Check if target already exists:**
   - If yes: Fail with error, suggest renaming existing archive or using different date
   - If no: Move the change directory to archive

   ```bash
   mv openspec/changes/<name> openspec/changes/archive/YYYY-MM-DD-<name>
   ```

6. **Update feature documentation**

   If a `docs/features/README.md` exists in the project root, update the corresponding feature doc:

   a. Read the **Spec-to-Feature Mapping** section from `docs/features/README.md` to find which feature doc corresponds to the change name (or the delta spec names).

   b. Identify the matching feature doc file. A change may map to a feature doc in two ways:
      - The change name directly matches a spec name in the mapping (e.g., change `lead-management` → `lead-management.md`)
      - The change's delta specs match spec names in the mapping

   c. If a matching feature doc is found:
      - Read the current feature doc
      - Read the main spec(s) that were synced (from `openspec/specs/<spec-name>/spec.md`)
      - Update the feature doc to reflect any new, changed, or removed features from the spec
      - Preserve the document structure (heading hierarchy, Specs section, Features section, Planned sections)
      - Move features from "Planned" sections to implemented sections if the spec now marks them as done
      - Add new features that appear in the updated spec
      - Keep the writing style consistent: short descriptions, bullet points for sub-features

   d. If no matching feature doc is found, **create one** at `docs/features/<change-name>.md` with:
      - Feature title and one-line summary
      - Standards references (GEMMA referentiecomponent URL, TEC RFP section, Forum Standaardisatie standards if applicable)
      - Overview section describing the feature
      - Key capabilities as bullet points (from the spec requirements)

   e. **Update the feature overview table** in `docs/features/README.md`:
      - Add a row for the new/updated feature in the Features table
      - Each row must have: Feature name, short summary (max 1 line), Standards column (GEMMA/TEC/ZGW references), link to feature doc
      - If `docs/features/README.md` doesn't exist, create it with:
        - App name and description
        - Standards Compliance table (GEMMA components, TEC sections, Forum Standaardisatie standards)
        - Features table with all implemented features

   **Standards references to include where applicable:**
   - GEMMA referentiecomponent URL (from `gemmaonline.nl`)
   - TEC RFP template section numbers
   - ZGW API standard references
   - Forum Standaardisatie 'Pas toe of leg uit' standards
   - ISO/NEN standards (27001, 15489, etc.)

7. **Close GitHub tracking issue** (if plan.json exists)

   Read `openspec/changes/<name>/plan.json` (from its new archive location).

   If `tracking_issue` is set, close it with a comment:
   - **MCP (preferred):** GitHub MCP `update_issue` → `{owner, repo, issue_number: <tracking_issue>, state: "closed"}`, then `add_issue_comment` → `{owner, repo, issue_number: <tracking_issue>, body: "✓ Change archived: openspec/changes/archive/YYYY-MM-DD-<name>/"}`
   - **CLI (fallback):** `gh issue close <tracking_issue> --repo <repo> --comment "✓ Change archived: openspec/changes/archive/YYYY-MM-DD-<name>/"`

   If individual task issues are still open (status != "done"), close them too:
   - **MCP (preferred):** GitHub MCP `update_issue` → `{owner, repo, issue_number: <task_issue>, state: "closed"}`, then `add_issue_comment` → `{..., body: "✓ Archived with change"}`
   - **CLI (fallback):** `gh issue close <task_issue> --repo <repo> --comment "✓ Archived with change"`

8. **Display summary and ask what's next**

   Show archive completion summary including:
   - Change name
   - Schema that was used
   - Archive location
   - Spec sync status (synced / sync skipped / no delta specs)
   - GitHub issues closed (if plan.json existed)
   - Note about any warnings (incomplete artifacts/tasks)

   Then use **AskUserQuestion** to ask:

   > "Change archived! What would you like to do next?"

   Options:
   - **Start a new change** (`/opsx:new`) — begin working on the next feature or fix
   - **Explore ideas first** (`/opsx:explore`) — think through what to build next before committing
   - **Run feature counsel** (`/opsx:feature-counsel`) — get multi-persona analysis of what to build next
   - **Done for now** — end the session

**Output On Success**

```
## Archive Complete

**Change:** <change-name>
**Schema:** <schema-name>
**Archived to:** openspec/changes/archive/YYYY-MM-DD-<name>/
**Specs:** ✓ Synced to main specs
**Feature docs:** ✓ Updated docs/features/<feature-file>.md

All artifacts complete. All tasks complete.
```

**Output On Success (No Delta Specs)**

```
## Archive Complete

**Change:** <change-name>
**Schema:** <schema-name>
**Archived to:** openspec/changes/archive/YYYY-MM-DD-<name>/
**Specs:** No delta specs
**Feature docs:** ✓ Updated docs/features/<feature-file>.md (or "No matching feature doc")

All artifacts complete. All tasks complete.
```

**Output On Success With Warnings**

```
## Archive Complete (with warnings)

**Change:** <change-name>
**Schema:** <schema-name>
**Archived to:** openspec/changes/archive/YYYY-MM-DD-<name>/
**Specs:** Sync skipped (user chose to skip)
**Feature docs:** ✓ Updated docs/features/<feature-file>.md (or "Skipped — no mapping found")

**Warnings:**
- Archived with 2 incomplete artifacts
- Archived with 3 incomplete tasks
- Delta spec sync was skipped (user chose to skip)

Review the archive if this was not intentional.
```

**Output On Error (Archive Exists)**

```
## Archive Failed

**Change:** <change-name>
**Target:** openspec/changes/archive/YYYY-MM-DD-<name>/

Target archive directory already exists.

**Options:**
1. Rename the existing archive
2. Delete the existing archive if it's a duplicate
3. Wait until a different date to archive
```

**What's Next**

The change is archived. Start your next change with:
- `/opsx:new` — start a new change
- `/opsx:explore` — explore ideas before starting

**Guardrails**
- Always prompt for change selection if not provided
- Use artifact graph (openspec status --json) for completion checking
- Don't block archive on warnings - just inform and confirm
- Preserve .openspec.yaml when moving to archive (it moves with the directory)
- Show clear summary of what happened
- If sync is requested, use /opsx:sync approach (agent-driven)
- If delta specs exist, always run the sync assessment and show the combined summary before prompting
