# Multi-Step Wizard Flow

## What Open Forms Does

### Step Architecture
- Forms consist of ordered `FormStep` instances
- Each step wraps a `FormDefinition` (the actual fields)
- Steps can be made non-applicable dynamically via logic rules
- First step must always be applicable

### Navigation
- Progress indicator shows current position (configurable `show_progress_indicator`)
- Summary progress: short "Step X of Y" display (`show_summary_progress`)
- Previous/Next/Save buttons with configurable text per step and per form
- Global defaults in `GlobalConfiguration`, overridable per form and per step

### Step Lifecycle
1. User loads form -> first applicable step shown
2. User fills fields, frontend sends data for logic evaluation
3. Logic may hide/show fields, skip steps, disable next button
4. User clicks Next -> data validated and saved as `SubmissionStep`
5. Repeat until last step
6. Overview page shown (unless `submission_allowed = yes_without_overview`)
7. User confirms -> submission completed

### Submission State
- `SubmissionState` dataclass tracks form_steps + submission_steps
- `get_last_completed_step()` finds where user left off
- Step index lookups by UUID for navigation
- Steps can be revisited and modified

### Form Suspension
- `suspension_allowed` flag on Form
- Suspended submissions saved with `suspended_on` timestamp
- Resume link sent via email
- Session-based access control for resuming

### Submission Allowed Options
- `yes` -- Normal flow with overview page
- `yes_without_overview` -- Skip overview, submit immediately after last step
- `no_with_overview` -- Show overview but block submission
- `no_without_overview` -- Block without overview

## Already in Procest

- Pipeline stages in Pipelinq provide multi-step concept
- Stage transitions are sequential

## Not Yet in Procest

- **Form wizard with progress indicator** -- No citizen-facing multi-step form UI
- **Dynamic step skipping** -- No conditional step applicability
- **Step-level data save** -- No per-step data persistence during form filling
- **Form suspension/resume** -- No save-and-continue-later flow
- **Overview page** -- No review-before-submit screen
- **Configurable navigation text** -- No per-step button label customization
