## ADDED Requirements

### Requirement: REQ-CVUI-001 Rule Builder in Placement Settings

Authors MUST be able to create, edit and delete conditional visibility rules on a widget placement they own from the widget settings panel, without using the raw API. The builder MUST operate over the existing conditional-visibility CRUD endpoints and MUST emit the canonical rule shape defined by the `conditional-visibility` spec. It MUST NOT introduce a new persistence path or alter the stored rule shape.

#### Scenario: Visibility section appears in the settings panel
- GIVEN author "alice" owns a dashboard with widget placement id 10
- WHEN she opens the placement/widget settings modal
- THEN a "Visibility" section MUST render the `ConditionalVisibilityEditor` component
- AND it MUST load the placement's existing rules via `GET /api/widgets/10/rules`
- AND each loaded rule MUST render as a `VisibilityRuleRow` with its `ruleType`, operands and include/exclude state populated

#### Scenario: Add a group inclusion rule through the UI
- GIVEN the Visibility section is open for placement id 10 with no rules
- WHEN alice adds a rule, selects type `group`, picks groups ["marketing", "sales"], sets it to include, and saves
- THEN the editor MUST send `POST /api/widgets/10/rules` with body `{"ruleType":"group","ruleConfig":{"groups":["marketing","sales"]},"isInclude":true}`
- AND the persisted `ruleConfig` MUST match the canonical shape from the `conditional-visibility` spec (camelCase, `groups` array)
- AND on HTTP 201 the new rule MUST appear as a row in the include section

#### Scenario: Edit an existing rule through the UI
- GIVEN placement id 10 has rule id 5 of type `group` with `ruleConfig {"groups":["marketing"]}`
- WHEN alice adds "management" to the group operand and saves the row
- THEN the editor MUST send `PUT /api/rules/5` with the updated `ruleConfig`
- AND the row MUST reflect the updated groups on HTTP 200

#### Scenario: Delete a rule through the UI
- GIVEN placement id 10 has rule id 5
- WHEN alice removes that row and confirms
- THEN the editor MUST send `DELETE /api/rules/5`
- AND the row MUST disappear from the editor on HTTP 200

#### Scenario: Editor does not change evaluation semantics
- GIVEN any rule created, edited or deleted through the editor
- WHEN the dashboard is subsequently rendered
- THEN visibility MUST be evaluated by the unchanged `ConditionalService` / `VisibilityChecker` pipeline
- AND the editor MUST NOT introduce any alternative evaluation, storage, or rule shape

### Requirement: REQ-CVUI-002 Per-Rule Row Editor for All Four Rule Types

The `VisibilityRuleRow` component MUST let an author configure any of the four supported rule types (`group`, `time`, `date`, `attribute`) with type-appropriate operand fields and an include/exclude toggle, emitting the canonical `ruleConfig` shape for that type.

#### Scenario: Group row operands
- GIVEN a rule row with type `group`
- WHEN the author selects groups ["marketing", "sales"]
- THEN the row MUST emit `ruleConfig {"groups":["marketing","sales"]}`

#### Scenario: Time row operands with day-of-week
- GIVEN a rule row with type `time`
- WHEN the author sets startTime "09:00", endTime "17:00" and days ["mon","tue","wed","thu","fri"]
- THEN the row MUST emit `ruleConfig {"startTime":"09:00","endTime":"17:00","days":["mon","tue","wed","thu","fri"]}` using camelCase keys

#### Scenario: Date row operands with open-ended range
- GIVEN a rule row with type `date`
- WHEN the author sets startDate "2026-12-01" and leaves endDate empty
- THEN the row MUST emit `ruleConfig {"startDate":"2026-12-01"}` and MUST NOT emit an empty `endDate` key

#### Scenario: Attribute row operands
- GIVEN a rule row with type `attribute`
- WHEN the author selects attribute "language", operator "equals", value "nl"
- THEN the row MUST emit `ruleConfig {"attribute":"language","operator":"equals","value":"nl"}`

#### Scenario: Include/exclude toggle
- GIVEN a rule row of any type
- WHEN the author toggles it to exclude
- THEN the row MUST emit `isInclude: false`
- AND the row MUST move to the editor's "Hide when…" section

### Requirement: REQ-CVUI-003 Legible Include/Exclude Semantics

The editor MUST surface the existing engine's include=OR and exclude=AND semantics clearly so an author can predict a placement's visibility from the layout, not from hidden boolean flags.

#### Scenario: Include rules grouped under an OR heading
- GIVEN placement id 10 has two include rules and one exclude rule
- WHEN the Visibility section renders
- THEN the two include rules MUST appear under a heading conveying "Show when ANY of these match" (OR)
- AND the exclude rule MUST appear under a heading conveying "Hide when ANY of these match" (AND-overrides)

#### Scenario: Semantics conveyed without relying on colour
- GIVEN the include and exclude sections are rendered
- WHEN a colour-blind author views the panel
- THEN the include/exclude distinction MUST be conveyed by heading text and layout, not colour alone (WCAG 2.1 AA 1.4.1)

#### Scenario: Empty state explains default visibility
- GIVEN placement id 10 has `isVisible: 1` and no rules
- WHEN the Visibility section renders
- THEN it MUST state that with no rules the widget is always shown (matching the engine's no-rules-means-visible behaviour)

### Requirement: REQ-CVUI-004 Preview As Audience and Date

The editor MUST provide a "preview as audience / date" affordance that shows the effective visibility of the current rule set for an author-chosen `(groups, datetime)` context before the rules are published, so an author can catch a mis-scoped rule (e.g. an exclude rule that hides the widget from everyone) prior to saving.

#### Scenario: Preview shows visible for a matching audience
- GIVEN the editor holds one include group rule with groups ["marketing"]
- WHEN the author previews as groups ["marketing"] at datetime "2026-07-23T14:30"
- THEN `useVisibilityPreview` MUST call `POST /api/visibility/preview` with the current rule set and that context
- AND the result MUST display "Visible" with an icon and text (not colour alone)
- AND the matched include rule MUST be indicated

#### Scenario: Preview shows hidden for a non-matching audience
- GIVEN the editor holds one include group rule with groups ["marketing"]
- WHEN the author previews as groups ["engineering"] at any datetime
- THEN the result MUST display "Hidden"
- AND no include rule MUST be indicated as matched

#### Scenario: Preview reflects an exclude override
- GIVEN the editor holds an include group rule (groups ["marketing"], matches) and an exclude date rule (2026-07-01..2026-07-31)
- WHEN the author previews as groups ["marketing"] at datetime "2026-07-15T10:00"
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

### Requirement: REQ-CVUI-005 Preview Endpoint Reuses the Render-Time Evaluation Path and Never Persists

The `POST /api/visibility/preview` endpoint MUST be read-only and MUST evaluate the supplied rule set against the supplied `(groups, datetime)` context through the SAME evaluation code path used at render time, so a preview verdict can never diverge from the visibility the dashboard will actually produce for that context. It MUST NOT write to the database and MUST validate its input server-side.

#### Scenario: Endpoint delegates to the shared evaluation service
- GIVEN a preview request `{"rules":[…], "context":{"groups":["marketing"], "datetime":"2026-07-15T10:00"}}`
- WHEN `VisibilityPreviewController` handles it
- THEN it MUST delegate to the same `VisibilityChecker` / `RuleEvaluatorService` (via `ConditionalService`) used by render-time `isWidgetVisible()`
- AND it MUST NOT re-implement or fork the include/exclude combination logic

#### Scenario: Preview verdict matches render-time verdict for identical inputs
- GIVEN a rule set R and a context C = (groups G, datetime D)
- AND a placement whose stored rules equal R rendered for a user in groups G at server time D
- WHEN both the preview endpoint and the render-time pipeline evaluate their inputs
- THEN the preview `visible` verdict MUST equal the render-time visibility verdict
- AND this equality MUST hold because both paths execute the same evaluation code, not because the preview reproduces the expected result independently

#### Scenario: Preview persists nothing
- GIVEN a preview request for placement-independent rule set R
- WHEN the endpoint responds
- THEN no row MUST be inserted, updated or deleted in `oc_launchpad_conditional_rules`
- AND the response MUST return `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}` only

#### Scenario: Preview rejects an invalid rule set
- GIVEN a preview request containing a rule with `ruleType` "weather"
- WHEN the endpoint validates the input
- THEN it MUST return HTTP 400 indicating the ruleType is invalid
- AND only `group`, `time`, `date`, and `attribute` MUST be accepted

#### Scenario: Preview requires an authenticated user
- GIVEN the endpoint is declared `#[NoAdminRequired]`
- WHEN an unauthenticated request is made
- THEN Nextcloud MUST reject it before the controller runs
- AND the endpoint MUST NOT be reachable as a public page
