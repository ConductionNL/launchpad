# Delta: conditional-visibility-editor

## ADDED Requirements

### Requirement: The rules dialog as LpZichtbaarheid draws it (REQ-CVUI-006)

The dialog SHALL be titled "Who sees this widget?" under "<widget> · <dashboard>", SHALL offer "If all rules match" and "If one rule matches" as choice cards, SHALL show one card per rule with its kind (Groups, Times, Period, Profile field), its value and Edit and delete buttons, then "Add rule", and SHALL end with "View as": a person and a moment, and a sentence that says whether that person sees the widget and why.

#### Scenario: View as Sanne

- **GIVEN** the rules ask for group KCC and Monday to Friday 08:00 to 17:00
- **WHEN** the editor picks Sanne de Vries and Tuesday 13 October 10:00
- **THEN** the sentence reads that Sanne sees this widget, because she is in KCC and the moment is within the times

### Requirement: The dialog is in the widget's menu (REQ-CVUI-007)

In edit mode the widget's menu SHALL hold "Who sees this widget?", which opens the dialog, so it is reachable by keyboard and not only by right-click.

#### Scenario: From the menu

- **WHEN** the editor opens the menu of the News widget and chooses Who sees this widget?
- **THEN** the dialog opens for that widget

## MODIFIED Requirements

### Requirement: REQ-CVUI-004 Preview As Audience and Date

The editor MUST provide a "View as" affordance that shows the effective visibility of the current rule set for one author-chosen person at one author-chosen moment, before the rules are published, so an author can catch a mis-scoped rule (e.g. an exclude rule that hides the widget from everyone) prior to saving. The person defaults to the author; the person's own groups and profile values are resolved on the server. The preview MUST use the placement's match mode (all or one rule, REQ-VIS-014) and MUST say in one sentence whether that person sees the widget and why (REQ-CVUI-006).

#### Scenario: Preview shows visible for a matching audience
- GIVEN the editor holds one include group rule with groups ["marketing"]
- WHEN the author views as a person in "marketing" at datetime "2026-07-23T14:30"
- THEN `useVisibilityPreview` MUST call `POST /api/visibility/preview` with the current rule set, that person as `context.userId`, that moment, the match mode and the placement id
- AND the result MUST display "Visible" with an icon and text (not colour alone)
- AND the matched include rule MUST be indicated
- AND the sentence MUST say that the person sees this widget, because the person is in "marketing"

#### Scenario: Preview shows hidden for a non-matching audience
- GIVEN the editor holds one include group rule with groups ["marketing"]
- WHEN the author views as a person in "engineering" only, at any moment
- THEN the result MUST display "Hidden"
- AND no include rule MUST be indicated as matched
- AND the sentence MUST say that the person does not see this widget, because the person is not in "marketing"

#### Scenario: Preview reflects an exclude override
- GIVEN the editor holds an include group rule (groups ["marketing"], matches) and an exclude date rule (2026-07-01..2026-07-31)
- WHEN the author views as a person in "marketing" at datetime "2026-07-15T10:00"
- THEN the result MUST display "Hidden"
- AND the matched exclude rule MUST be indicated as the reason

#### Scenario: Preview evaluates unsaved edits
- GIVEN the author has added a rule row but has not yet saved it
- WHEN the author runs preview
- THEN the preview MUST evaluate the in-editor (unsaved) rule set
- AND the preview MUST NOT persist any rule

#### Scenario: Preview uses the same rule shape as the editor emits
- GIVEN the editor's rule rows and the preview request
- WHEN `useVisibilityPreview` builds the request body
- THEN it MUST send the exact `ruleConfig` shape the rows emit, with no translation layer that could drift from the saved shape

#### Scenario: Viewing as another person needs the rules editor
- GIVEN the author may not edit the placement's rules
- WHEN a preview names another person as `context.userId`
- THEN the endpoint MUST answer HTTP 403 and evaluate nothing
