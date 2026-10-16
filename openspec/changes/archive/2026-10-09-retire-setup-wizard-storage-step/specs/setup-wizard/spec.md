# Delta: setup-wizard

## RENAMED Requirements

- FROM: `### Requirement: REQ-WIZ-002 Multi-Step Wizard Flow with 7 Steps`
- TO: `### Requirement: REQ-WIZ-002 Multi-Step Wizard Flow with 6 Steps`

- FROM: `### Requirement: REQ-WIZ-004 Step 3 — Group Priority Order (Embedded Component Reuse)`
- TO: `### Requirement: REQ-WIZ-004 Step 2 — Group Priority Order (Embedded Component Reuse)`

- FROM: `### Requirement: REQ-WIZ-005 Step 4 — Demo Data Installation (Embedded Component Reuse)`
- TO: `### Requirement: REQ-WIZ-005 Step 3 — Demo Data Installation (Embedded Component Reuse)`

- FROM: `### Requirement: REQ-WIZ-006 Step 5 — Admin Roles (Optional, Depends on admin-roles Capability)`
- TO: `### Requirement: REQ-WIZ-006 Step 4 — Admin Roles (Optional, Depends on admin-roles Capability)`

- FROM: `### Requirement: REQ-WIZ-007 Step 6 — Footer Configuration (Optional, Depends on footer-customization Capability)`
- TO: `### Requirement: REQ-WIZ-007 Step 5 — Footer Configuration (Optional, Depends on footer-customization Capability)`

## REMOVED Requirements

### Requirement: REQ-WIZ-003 Step 2 — Storage Backend Choice with GroupFolder Dependency Tooltip

**Reason**: decision 131 (9 Oct 2026) retired the storage step; the group-folder backend it chose between is gone.

**Migration**: none. A stored `content_storage` row stays unread.

## MODIFIED Requirements

### Requirement: REQ-WIZ-001 Detect First-Run State via Admin Setting Flag

The system MUST detect whether a LaunchPad instance is being initialized for the first time by consulting the `launchpad.setup_wizard_complete` boolean flag. When the flag is `false`, the admin section MUST display a banner prompting the admin to run the wizard.

> NOTE: This is a net-new LaunchPad capability with no counterpart in the source app. The source ships CLI-only setup via `the source app:setup`; no frontend first-run detection, wizard route, or onboarding component exists in the source. LaunchPad adds this flag and banner on top of the same underlying service primitives.

#### Scenario: Fresh instance shows banner
- GIVEN a freshly installed LaunchPad with `launchpad.setup_wizard_complete = false`
- WHEN an NC admin opens `/apps/launchpad/admin/dashboards`
- THEN the page MUST display a prominent banner: "Run setup wizard" with a button linking to the wizard flow
- AND the banner MUST remain visible until the admin clicks "Finish" in the wizard

#### Scenario: Completed instance hides banner
- GIVEN a LaunchPad instance with `launchpad.setup_wizard_complete = true`
- WHEN an NC admin opens `/apps/launchpad/admin/dashboards`
- THEN the banner MUST NOT be displayed
- AND the admin section loads normally

#### Scenario: Banner includes descriptive text
- GIVEN a fresh LaunchPad instance showing the setup banner
- WHEN the admin views the banner
- THEN the banner MUST include text explaining: "Get your intranet started: configure groups, install demo data, and set up admin roles."
- NOTE: Banner style and wording are implementation-specific; requirement mandates presence and visibility only

#### Scenario: Banner has explicit "Run setup wizard" call-to-action
- GIVEN the banner is displayed
- WHEN the admin clicks the "Run setup wizard" button
- THEN the wizard modal MUST open and begin at Step 1 (Welcome)

#### Scenario: Admin can dismiss and re-access banner
- GIVEN an admin who dismissed the banner or navigated away
- WHEN the admin returns to `/apps/launchpad/admin/dashboards`
- THEN if `launchpad.setup_wizard_complete = false`, the banner MUST reappear

### Requirement: REQ-WIZ-002 Multi-Step Wizard Flow with 6 Steps

The wizard MUST guide the admin through a linear, skippable sequence of 6 steps, each with Skip / Back / Next buttons. Only the "Finish" button on Step 6 marks the wizard complete.

> NOTE: Steps 2–5 each embed the canonical admin UI component from the corresponding sibling capability (`group-priority-order`, `demo-data-showcases`, `admin-roles`, `footer-customization`). The wizard is a thin orchestrator — it owns step sequencing and the completion flag; each sibling capability owns its own settings persistence. No settings surfaces are duplicated.

#### Scenario: Wizard starts at Step 1
- GIVEN the banner's "Run setup wizard" button is clicked
- WHEN the wizard modal opens
- THEN Step 1 (Welcome / Overview) MUST be displayed
- AND the step counter MUST show "1 / 7"

#### Scenario: Next button advances to next step
- GIVEN the wizard is on Step 1
- WHEN the admin clicks "Next"
- THEN the wizard advances to Step 2 (Group order)
- AND the step counter MUST show "2 / 7"
- AND the Back button MUST become active

#### Scenario: Back button returns to previous step
- GIVEN the wizard is on Step 3
- WHEN the admin clicks "Back"
- THEN the wizard returns to Step 2
- AND no data loss occurs (Step 3 values are preserved if re-visited)

#### Scenario: Skip button jumps to next step without commitment
- GIVEN the wizard is on Step 4 (Admin roles, optional)
- WHEN the admin clicks "Skip"
- THEN the wizard advances to Step 5
- AND the wizard completion status for Step 4 is marked "skipped" (not "done")
- NOTE: Skipping a step does not prevent the wizard from eventually marking complete

#### Scenario: Step 1 has no form; only explanatory text
- GIVEN Step 1 (Welcome) is displayed
- WHEN the admin views the step
- THEN the step MUST show text explaining the wizard's purpose: "Configure your LaunchPad instance with group ordering, demo data, admin roles, and footer settings."
- AND there are no form fields to fill
- AND clicking "Next" advances without persisting any choice

#### Scenario: Step 6 shows summary and Finish button
- GIVEN the wizard reaches Step 6 (Done)
- WHEN the admin views the step
- THEN the step MUST display a summary: "Your setup is complete. Click Finish to save changes and dismiss the setup banner."
- AND a "Finish" button (not "Next") MUST be present
- AND clicking "Finish" executes the completion action

#### Scenario: Finish button completes wizard
- GIVEN the admin is on Step 6 with the "Finish" button visible
- WHEN the admin clicks "Finish"
- THEN the system MUST set `launchpad.setup_wizard_complete = true`
- AND the wizard modal MUST close
- AND the admin section MUST reload, banner gone

#### Scenario: Wizard progress persists across Back navigation
- GIVEN the wizard is on Step 3 after a group order was chosen on Step 2
- WHEN the admin clicks Back to Step 2, then clicks Next to Step 3
- THEN the group order chosen on Step 2 MUST still be present (no data loss)

### Requirement: REQ-WIZ-004 Step 2 — Group Priority Order (Embedded Component Reuse)

Step 2 MUST embed the existing `group-priority-order` admin UI component, allowing the admin to configure the priority order of Nextcloud groups for dashboard routing.

#### Scenario: Step 2 embeds existing group-priority-order admin component
- GIVEN Step 2 is displayed
- WHEN the admin views the step
- THEN the existing `group-priority-order` admin UI MUST be visible and functional within the wizard modal
- NOTE: The component is not duplicated; the wizard embeds the canonical admin component

#### Scenario: Group order choice persists immediately
- GIVEN the admin reorders groups in Step 2 (e.g., "engineering" moved above "sales")
- WHEN the admin clicks Next
- THEN the system MUST immediately persist the new order to `launchpad.group_priority_order`
- AND navigating away does not lose the change

#### Scenario: Step 2 allows drag-and-drop reordering (existing UI behavior)
- GIVEN the group-priority-order component is displayed
- WHEN the admin drags a group to a new position
- THEN the reorder MUST be reflected in the UI
- NOTE: Exact interaction (drag, arrow buttons, etc.) is determined by the embedded component's design

### Requirement: REQ-WIZ-005 Step 3 — Demo Data Installation (Embedded Component Reuse)

Step 3 MUST embed the existing `demo-data-showcases` admin UI component, allowing the admin to optionally select and install demo packages.

#### Scenario: Step 3 embeds demo-data-showcases admin component
- GIVEN Step 3 is displayed
- WHEN the admin views the step
- THEN the existing `demo-data-showcases` admin UI MUST be visible within the wizard modal
- AND the component displays all available demo packages with checkboxes
- NOTE: The component is the canonical admin UI; wizard embeds it without duplication

#### Scenario: Demo data installation is optional
- GIVEN Step 3 is displayed with zero demo packages selected
- WHEN the admin clicks "Skip" or "Next"
- THEN the wizard MUST advance without requiring any demo to be installed
- NOTE: Step 3 is not skippable via a Skip button; all steps are traversed. However, selecting zero demos and clicking Next is equivalent to skipping.

#### Scenario: Checked demos are installed on Next
- GIVEN the admin selects checkboxes for "Engineering Demo" and "Sales Demo" on Step 3
- WHEN the admin clicks "Next"
- THEN the system MUST trigger the installation of both demo packages
- AND the wizard advances to Step 4 (installation happens asynchronously or blocks until done, implementation choice)
- NOTE: The demo-data-showcases component handles the actual installation logic

#### Scenario: Demo selection is persisted
- GIVEN the admin selected "Engineering Demo" on Step 3 and advanced to Step 4
- WHEN the admin navigates Back to Step 3
- THEN the "Engineering Demo" checkbox MUST remain checked
- AND no re-installation happens (idempotent)

### Requirement: REQ-WIZ-006 Step 4 — Admin Roles (Optional, Depends on admin-roles Capability)

Step 4 MUST allow the admin to optionally assign the "Dashboard Admin" role to one Nextcloud group. This step is skippable via a Skip button.

#### Scenario: Step 4 shows group selector for admin role
- GIVEN the `admin-roles` capability is available
- AND Step 4 is displayed
- WHEN the admin views the step
- THEN a dropdown or multi-select MUST display all Nextcloud groups
- AND a description MUST explain: "Assign 'Dashboard Admin' role to a group to delegate LaunchPad administration to group members."

#### Scenario: Admin can select one group for Dashboard Admin role
- GIVEN Step 4 is displayed with groups ["engineering", "sales", "marketing"]
- WHEN the admin selects "engineering"
- AND clicks "Next"
- THEN the system MUST call `RoleService::assignRole(groupId="engineering", role="admin", assignedBy=<current-admin>)`
- AND the role assignment MUST be created in `oc_launchpad_role_assignments`

#### Scenario: Step 4 is skippable
- GIVEN Step 4 is displayed
- WHEN the admin clicks "Skip"
- THEN no role assignment is made
- AND the wizard advances to Step 5
- AND the completion status for Step 4 MUST be marked "skipped"

#### Scenario: No group selected is equivalent to skip
- GIVEN Step 4 with no group selected
- WHEN the admin clicks "Next"
- THEN no role assignment is made (equivalent to Skip)
- AND the wizard advances to Step 5

#### Scenario: Step 4 unavailable if admin-roles capability missing
- GIVEN the `admin-roles` capability is NOT implemented or not available
- WHEN the wizard would display Step 4
- THEN Step 4 MUST be skipped automatically
- AND the step counter jumps from Step 3 to Step 5
- NOTE: Capability dependency is gracefully handled; missing capability does not break wizard

#### Scenario: Only one group can have admin role per wizard run
- GIVEN Step 4 is displayed
- WHEN the admin selects "engineering" and clicks "Next"
- THEN only the "engineering" group receives the admin role (not multiple groups)
- NOTE: If the admin wishes to assign admin role to multiple groups, they must run the wizard again or use the admin roles management UI directly

### Requirement: REQ-WIZ-007 Step 5 — Footer Configuration (Optional, Depends on footer-customization Capability)

Step 5 MUST allow the admin to optionally open and configure the footer using the "Structured mode" editor. This step is skippable via a Skip button.

#### Scenario: Step 5 shows footer editor
- GIVEN the `footer-customization` capability is available
- AND Step 5 is displayed
- WHEN the admin views the step
- THEN the "Structured mode" footer editor MUST be visible and functional
- AND a description MUST explain: "Customize the footer content and appearance for your intranet."

#### Scenario: Footer edits persist immediately
- GIVEN the admin edits footer content in Step 5
- WHEN the admin clicks "Next"
- THEN the footer configuration MUST be saved to `launchpad.footer_config` or equivalent setting
- AND navigating away does not lose the changes

#### Scenario: Step 5 is skippable
- GIVEN Step 5 is displayed
- WHEN the admin clicks "Skip"
- THEN no footer changes are made
- AND the wizard advances to Step 6
- AND the completion status for Step 5 MUST be marked "skipped"

#### Scenario: Step 5 unavailable if footer-customization capability missing
- GIVEN the `footer-customization` capability is NOT implemented or not available
- WHEN the wizard would display Step 5
- THEN Step 5 MUST be skipped automatically
- AND the step counter jumps from Step 4 to Step 6
- NOTE: Capability dependency is gracefully handled; missing capability does not break wizard

### Requirement: REQ-WIZ-008 Get Wizard State via API Endpoint

The system MUST expose a `GET /api/admin/setup-wizard/state` endpoint that returns the wizard's completion status, current recommended step for re-runs, and per-step status.

> NOTE: Auto-launch behaviour (opening the wizard automatically when `setup_wizard_complete = false`) is intentionally SHOULD/MAY, not MUST. CLI-provisioned installs may set settings incrementally mid-`occ` run, meaning an admin who views the page before the CLI command finishes would see `complete = false` with settings partially applied. The banner-link fallback is always available and is the reliable path; auto-launch is a UX convenience for fresh browser sessions only.

#### Scenario: Endpoint returns complete state on fresh instance
- GIVEN a fresh LaunchPad instance with `launchpad.setup_wizard_complete = false`
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN the response MUST be HTTP 200 with JSON:
  ```json
  {
    "complete": false,
    "currentRecommendedStep": 1,
    "stepStatuses": {
      "1": "done",
      "2": "pending",
      "3": "pending",
      "4": "pending",
      "5": "pending",
      "6": "pending"
    }
  }
  ```
- NOTE: Step 1 is always "done" (no choice required). Steps 2–6 start "pending".

#### Scenario: Endpoint returns recommended step for re-runs
- GIVEN a LaunchPad instance where the group order is not yet set
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN the response MUST include `currentRecommendedStep: 2`
- NOTE: The heuristic picks the first step with status != "done"

#### Scenario: Endpoint returns complete=true when wizard finished
- GIVEN a LaunchPad instance with `launchpad.setup_wizard_complete = true`
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN the response MUST include `complete: true`
- AND `currentRecommendedStep` MAY be 1 (optional) or reflect the last visited step (implementation choice)

#### Scenario: Non-admin requests receive 403
- GIVEN a non-admin user
- WHEN they call `GET /api/admin/setup-wizard/state`
- THEN the system MUST return HTTP 403 (Forbidden)

#### Scenario: Endpoint includes skipped step status
- GIVEN the wizard has been run with Step 4 skipped
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN the response MUST include `"4": "skipped"` in stepStatuses
- NOTE: Skipped status indicates the step was traversed but no action taken; does not block wizard completion

#### Scenario: A stored storage choice from the retired step is ignored
- GIVEN an instance that still holds a `content_storage` row written by the retired storage step
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN `stepStatuses` MUST hold exactly six entries
- AND the stored row MUST NOT change any step status or `currentRecommendedStep`

### Requirement: REQ-WIZ-010 CLI Command for Non-Interactive Setup (IaC-Friendly)

The system MUST expose a CLI command `php occ launchpad:setup` that accepts a `--config=/path/setup.yaml` argument to perform wizard setup non-interactively, ideal for Infrastructure-as-Code deployments.

> NOTE: The YAML config schema (fields: `group_priority_order`, `demo_packages`, `admin_role_group`, `footer_config`) is formally defined in the `cli-commands` sibling spec. Optional steps (admin roles, footer) may be omitted from the config file entirely; omitting them is equivalent to skipping those steps in the wizard and does not cause an error.

#### Scenario: CLI command accepts YAML config file
- GIVEN a YAML file `/tmp/setup.yaml` with content:
  ```yaml
  group_priority_order: ["engineering", "sales"]
  demo_packages: ["engineering-demo", "sales-demo"]
  admin_role_group: "engineering"
  footer_config:
    layout: "structured"
    items: [...]
  ```
- WHEN an admin runs `php occ launchpad:setup --config=/tmp/setup.yaml`
- THEN the system MUST:
  - Set `launchpad.group_priority_order = ["engineering", "sales"]` (Step 2)
  - Install "engineering-demo" and "sales-demo" (Step 3)
  - Assign "Dashboard Admin" role to "engineering" (Step 4)
  - Set `launchpad.footer_config` with provided config (Step 5)
  - Set `launchpad.setup_wizard_complete = true` (Step 6)

#### Scenario: CLI command validates YAML schema
- GIVEN a YAML file whose top level is not a map, or whose `group_priority_order` is not a list
- WHEN `php occ launchpad:setup --config=/tmp/setup.yaml` is run
- THEN the system MUST output an error starting with "Invalid setup.yaml:"
- AND the command MUST exit with non-zero status

#### Scenario: An old config that still sets storage_backend keeps working
- GIVEN a YAML file written for the seven-step wizard, with `storage_backend: "groupfolder"`
- WHEN `php occ launchpad:setup --config=/tmp/setup.yaml` is run
- THEN the command MUST print that `storage_backend` is no longer used and was ignored
- AND it MUST apply the other steps and exit with status 0
- AND it MUST NOT write `launchpad.content_storage`

#### Scenario: CLI command is verbose by default
- GIVEN the command runs successfully
- WHEN the output is examined
- THEN it MUST show progress: "Step 1: Welcome... done", "Step 2: Group order... done", etc.
- AND final message: "Setup wizard completed successfully."

#### Scenario: CLI command skips optional steps if not in config
- GIVEN a YAML file with only `group_priority_order`
- WHEN the command runs
- THEN Steps 4 and 5 MUST be skipped (no error)
- AND Step 2 MUST execute with the provided value
- AND the wizard completes

#### Scenario: CLI command is idempotent
- GIVEN a YAML config that was applied once
- WHEN the same command runs again with the same config
- THEN the system MUST return success (HTTP 200 equivalent)
- AND no duplicate role assignments or demo installations occur
- NOTE: The system MUST detect existing assignments/installations and skip them

### Requirement: REQ-WIZ-011 Wizard is Re-Runnable

The wizard MUST be re-runnable even after `launchpad.setup_wizard_complete = true`. Re-running MUST NOT undo earlier choices; it MUST re-walk the steps showing current state and allowing updates.

#### Scenario: Admin can run wizard again after completion
- GIVEN a LaunchPad instance with `launchpad.setup_wizard_complete = true`
- WHEN an NC admin navigates to `/apps/launchpad/admin` and clicks "Run setup wizard again" (or uses a Re-run button in a separate admin UI section)
- THEN the wizard modal MUST open
- AND all step values MUST reflect current settings (group order, demo data, etc.)
- AND the admin can make changes

#### Scenario: Re-running does not re-install demo packages
- GIVEN the instance has already installed "engineering-demo"
- AND the admin re-runs the wizard to Step 3
- WHEN the admin unchecks "engineering-demo" and clicks Next
- THEN the system MUST NOT re-install the demo (it's already there)
- NOTE: Demo installation is one-time; re-running the wizard does not trigger redundant installations. If the admin wishes to un-install, they must use a separate admin UI for demo management.

#### Scenario: Re-running preserves skipped steps
- GIVEN the wizard was run previously with Step 4 skipped
- AND the admin re-runs the wizard
- WHEN Step 4 is displayed
- THEN the step MUST show "Skip" button as before
- AND if the admin clicks Skip again, the behavior is consistent with the first run

#### Scenario: Step 1 is always done on re-run
- GIVEN a re-run of the wizard
- WHEN Step 1 is displayed
- THEN no new action is required; clicking Next immediately advances

#### Scenario: API endpoint reports re-runnable state
- GIVEN the instance has `launchpad.setup_wizard_complete = true`
- WHEN an NC admin calls `GET /api/admin/setup-wizard/state`
- THEN the response MUST indicate `complete: true`
- AND the admin UI MUST show a "Run setup wizard again" button (not a "Run setup wizard" banner)
- NOTE: The UI signals that re-running is available and safe

