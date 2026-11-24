# Delta: setup-wizard

## REMOVED Requirements

### Requirement: REQ-WIZ-002 Multi-Step Wizard Flow with 6 Steps

**Reason**: boards LpInstellen and LpBeheerAlgemeen draw five steps. REQ-WIZ-012 replaces it.

**Migration**: none; the steps that stay keep what they store.

### Requirement: REQ-WIZ-007 Step 5 — Footer Configuration (Optional, Depends on footer-customization Capability)

**Reason**: boards LpInstellen and LpBeheerAlgemeen draw a setup of five steps without a footer step. The footer is
set on the admin tab Organisatiemenu (board LpBeheerNavigatie).

**Migration**: none. A footer set earlier stays as it is; `footer_config` in the `occ launchpad:setup` file keeps
working.

## ADDED Requirements

### Requirement: REQ-WIZ-012 Multi-Step Wizard Flow with 5 Steps

The wizard MUST guide the admin through a linear, skippable sequence of 5 steps, as board LpInstellen draws them:
Welcome ("Welkom"), Groups ("Groepen", the group priority order), Examples ("Voorbeelden", the demo dashboards), Roles
("Rollen", the admin roles) and Done ("Klaar"). Each step has Skip / Back / Next buttons. Only the "Finish" button on
Step 5 marks the wizard complete. The wizard MUST NOT ask for storage or for the footer.

> NOTE: Steps 2 to 4 each embed the canonical admin UI component from the corresponding sibling capability (`group-priority-order`, `demo-data-showcases`, `admin-roles`). The wizard is a thin orchestrator: it owns step sequencing and the completion flag; each sibling capability owns its own settings persistence. No settings surfaces are duplicated.

#### Scenario: Wizard starts at Step 1
- GIVEN the banner's "Run setup wizard" button is clicked
- WHEN the wizard modal opens
- THEN Step 1 (Welcome) MUST be displayed
- AND the step counter MUST show "1 / 5"

#### Scenario: Next button advances to next step
- GIVEN the wizard is on Step 1
- WHEN the admin clicks "Next"
- THEN the wizard advances to Step 2 (Groups)
- AND the step counter MUST show "2 / 5"
- AND the Back button MUST become active

#### Scenario: Back button returns to previous step
- GIVEN the wizard is on Step 3
- WHEN the admin clicks "Back"
- THEN the wizard returns to Step 2
- AND no data loss occurs (Step 3 values are preserved if re-visited)

#### Scenario: Skip button jumps to next step without commitment
- GIVEN the wizard is on Step 4 (Roles, optional)
- WHEN the admin clicks "Skip"
- THEN the wizard advances to Step 5 (Done)
- AND the wizard completion status for Step 4 is marked "skipped" (not "done")

#### Scenario: Step 1 has no form; only explanatory text
- GIVEN Step 1 (Welcome) is displayed
- WHEN the admin views the step
- THEN the step MUST explain that the setup sets groups, example dashboards and roles in five steps, and that existing dashboards stay as they are
- AND there are no form fields to fill
- AND clicking "Next" advances without persisting any choice

#### Scenario: Step 5 shows summary and Finish button
- GIVEN the wizard reaches Step 5 (Done)
- WHEN the admin views the step
- THEN the step MUST display a summary: "Your setup is complete. Click Finish to save changes and dismiss the setup banner."
- AND a "Finish" button (not "Next") MUST be present

#### Scenario: Finish button completes wizard
- GIVEN the admin is on Step 5 with the "Finish" button visible
- WHEN the admin clicks "Finish"
- THEN the system MUST set `launchpad.setup_wizard_complete = true`
- AND the wizard modal MUST close
- AND the admin section MUST reload, banner gone

#### Scenario: No footer step
- GIVEN the admin walks the wizard from Step 1 to Step 5
- THEN no step shows the footer editor
- AND the wizard state endpoint reports five steps

#### Scenario: Wizard progress persists across Back navigation
- GIVEN the wizard is on Step 3 after a group order was chosen on Step 2
- WHEN the admin clicks Back to Step 2, then clicks Next to Step 3
- THEN the group order chosen on Step 2 MUST still be present (no data loss)
