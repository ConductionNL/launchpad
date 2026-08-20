# Conditional Visibility

Conditional visibility allows widget placements to be shown or hidden based on dynamic rules evaluated at render time.

## Rule Types

| Type | Config | Description |
|------|--------|-------------|
| `group` | `{"groups": ["admin"]}` | Match user's Nextcloud groups |
| `time` | `{"startTime": "09:00", "endTime": "17:00", "days": ["mon"]}` | Match time of day and day of week |
| `date` | `{"startDate": "2026-12-01", "endDate": "2026-12-31"}` | Match date range |
| `attribute` | `{"attribute": "language", "operator": "equals", "value": "nl"}` | Match user attribute |

## Logic

- **Include rules**: OR logic (at least one must match to show)
- **Exclude rules**: AND logic (any match hides the widget)
- No rules + isVisible=1: always shown
- isVisible=0: always hidden (overrides rules)

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/widgets/{id}/rules` | List rules for placement |
| POST | `/api/widgets/{id}/rules` | Add rule to placement |
| PUT | `/api/rules/{id}` | Update rule |
| DELETE | `/api/rules/{id}` | Delete rule |
| POST | `/api/visibility/preview` | Preview a rule set (see below) — read-only, persists nothing |

## Visibility rules & preview

The rules above are edited from the widget's right-click context menu →
**Visibility rules…**, which opens the `ConditionalVisibilityEditor`. Each
rule is a row (`VisibilityRuleRow`) where you pick a type (group / time /
date / attribute), fill in the type-specific fields, and choose whether the
rule **includes** or **excludes**:

- Rules are grouped under two headings that spell out the engine's logic
  directly: **"Show when ANY of these match"** (include rules, OR — at
  least one must match) and **"Hide when ANY of these match"** (exclude
  rules, AND — any single match hides the widget, overriding the include
  rules).
- With no rules at all, the widget is always shown — the editor states this
  explicitly rather than leaving an empty list ambiguous.

### Preview as audience / date

Before saving, use **Preview as audience / date** to pick a set of groups
and a moment in time and see the effective visibility for that context —
"Visible" or "Hidden", plus which rule(s) matched. This includes rows you
have added or edited but not yet saved, so a mis-scoped rule (e.g. an
exclude rule that would hide the widget from everyone) can be caught before
it goes live.

The preview endpoint (`POST /api/visibility/preview`) evaluates the
supplied rule set through the exact same evaluation pipeline used when the
dashboard is actually rendered — it cannot diverge from real visibility,
and it never writes to the database.

See the [`conditional-visibility` engine spec](../../openspec/specs/conditional-visibility/spec.md)
for the full rule-evaluation semantics (including known limitations such as
midnight-spanning time windows) and the
[`conditional-visibility-editor` spec](../../openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md)
for the editor/preview requirements.

## Screenshot

![Dashboard Overview](/screenshots/launchpad-dashboard-overview.png)
