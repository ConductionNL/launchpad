# Delta: conditional-visibility

## ADDED Requirements

### Requirement: All or one rule must match (REQ-VIS-014)

A widget placement SHALL store how its include rules combine in `ruleMatch`: `any` (one include rule that matches is enough, the behaviour of REQ-VIS-009) or `all` (every include rule must match). A placement without a stored mode SHALL read as `any`, so every existing dashboard stays as it is. Exclude rules SHALL keep hiding the widget when any of them matches, in both modes. The mode SHALL be evaluated in `VisibilityChecker`, the one place the rules are combined, at render time and in the preview. `PUT /api/widgets/{placementId}` SHALL accept only `any` or `all` (HTTP 400 otherwise), and only from the dashboard owner or an administrator (HTTP 403 otherwise). Copying a dashboard, a template and an export SHALL carry the mode.

#### Scenario: All rules must match

- **GIVEN** widget placement 10 has `ruleMatch: all` and two include rules: group KCC and Monday to Friday 08:00 to 17:00
- **AND** the user is in KCC and it is Saturday 10:00
- **WHEN** the dashboard is rendered
- **THEN** the widget MUST be hidden, because the time rule does not match

#### Scenario: One rule is enough

- **GIVEN** the same placement with `ruleMatch: any`
- **AND** the user is in KCC and it is Saturday 10:00
- **WHEN** the dashboard is rendered
- **THEN** the widget MUST be visible, because the group rule matches
