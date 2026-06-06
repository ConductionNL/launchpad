---
status: active
kind: frontend
cross-links:
  - openspec/specs/dashboard-sharing/spec.md
  - openspec/specs/admin-templates/spec.md
  - openspec/changes/launchpad-adopt-or-abstractions/design.md
---

# Runtime OR Consumption Specification

## Purpose

MyDash optionally surfaces data from OpenRegister (OR) in certain widgets.
This spec defines the rules that ALL OR-data widgets MUST follow to keep
MyDash usable on installations where OR is absent, unavailable, or returning
errors.

The central invariant: **MyDash is an OR-free app that can optionally
consume OR data at runtime.** OR is never a hard dependency.

---

## Requirements

@e2e exclude pure runtime/contract spec — scenarios assert manifest.json/info.xml/composer dependency contents, `useOrFeatureDetect()` composable gating, `?_lang=` query-param construction, tenant-scope headers, @nextcloud/axios usage, and OR-absent/5xx/recovery HTTP behaviour. None is a user-facing UI surface; these are unit-test + Newman concerns (no OR-data widget is shipped in launchpad to drive through the UI).

### Requirement: No install-time OR dependency (REQ-OR-001)

MyDash MUST NOT declare an install-time dependency on `openregister` or
`openconnector` in `appinfo/info.xml`, `composer.json`, or
`src/manifest.json`.

#### Scenario: Fresh install without OR boots normally

- GIVEN a Nextcloud instance with MyDash installed but OpenRegister absent
- WHEN an admin opens MyDash
- THEN the app MUST load without errors
- AND all dashboards, widgets, and admin settings MUST be fully functional
- AND no "missing dependency" warning or fatal error MUST appear in the
  Nextcloud log

#### Scenario: manifest.json dependencies array is empty

- GIVEN the `src/manifest.json` file for MyDash
- WHEN it is parsed
- THEN the `dependencies` array MUST be `[]`
- AND it MUST NOT contain `"openregister"` or `"openconnector"`

---

### Requirement: Runtime feature-detect (REQ-OR-002)

Any widget that fetches data from OR MUST first call `useOrFeatureDetect()`
and check `enabled.value` before issuing any OR API request.

#### Scenario: Widget skips OR fetch when OR is absent

- GIVEN a Nextcloud instance without OR installed
- WHEN an OR-data widget mounts
- THEN `useOrFeatureDetect().enabled.value` MUST be `false`
- AND the widget MUST NOT issue any HTTP request to `/apps/openregister/...`
- AND the widget MUST render its documented empty state

#### Scenario: Widget fetches OR data when OR is enabled

- GIVEN a Nextcloud instance with OR installed and enabled
- WHEN an OR-data widget mounts
- THEN `useOrFeatureDetect().enabled.value` MUST be `true`
- AND the widget MAY issue OR API requests
- AND it MUST pass `?_lang=` per REQ-OR-003

---

### Requirement: Language parameter on translatable OR requests (REQ-OR-003)

OR-data widgets that fetch translatable content MUST pass `?_lang=<locale>`
as a query parameter on every OR data request.

The locale MUST be derived from `document.documentElement.lang` (set by
Nextcloud from the user's language preference).

#### Scenario: Widget passes current user locale

- GIVEN the Nextcloud UI is rendered in Dutch (`<html lang="nl">`)
- WHEN an OR-data widget fetches a list of OR objects with translatable fields
- THEN the request URL MUST include `?_lang=nl` (or `&_lang=nl` when other params are present)
- AND OR MUST return the Dutch translation of the translatable fields

#### Scenario: Widget falls back to "en" when lang attribute is absent

- GIVEN the `<html>` element has no `lang` attribute
- WHEN an OR-data widget builds its OR request
- THEN it MUST send `?_lang=en` as a safe default

---

### Requirement: Tenant context for OR-data widgets (REQ-OR-004)

OR-data widgets that surface tenant-scoped OR data SHOULD consume
`useTenantContext()` from `@conduction/nextcloud-vue` to pass the active
tenant scope, once the composable is released as a versioned package
(tracking: `nextcloud-vue/openspec/changes/multi-tenancy-context`).

Until `useTenantContext()` is available:
- Widgets MAY omit tenant filtering.
- OR's server-side ACL still enforces per-session access controls.

#### Scenario: Widget passes tenant scope when context is available

- GIVEN `useTenantContext()` is available in the installed nc-vue version
- AND the current user is scoped to tenant `acme-corp`
- WHEN an OR-data widget fetches OR objects
- THEN the request MUST include the tenant scope header or parameter
  expected by OR
- AND only objects visible to `acme-corp` MUST appear in the results

---

### Requirement: Graceful empty state on OR absence or error (REQ-OR-005)

OR-data widgets MUST render a clearly labelled empty state whenever OR is
absent OR when OR returns a 4xx or 5xx response. The empty state MUST NOT
show a raw error message or stack trace.

#### Scenario: OR absent — empty state shown

- GIVEN OR is not installed
- WHEN an OR-data widget renders
- THEN the widget MUST show an `NcEmptyContent` (or equivalent) with a
  human-readable explanation such as "OpenRegister not available"
- AND no blank or spinner-only state MUST persist

#### Scenario: OR 5xx — widget degrades to empty state

- GIVEN OR is installed but returns HTTP 500 on a data fetch
- WHEN the OR-data widget catches the error
- THEN the widget MUST render the same empty state as the absent-OR case
- AND the error MUST be logged to the browser console at `console.warn` level
- AND no unhandled Promise rejection MUST appear

#### Scenario: OR recovers after transient failure

- GIVEN an OR-data widget showed the empty-state due to a 503
- AND OR subsequently becomes healthy
- WHEN the user navigates away from and back to the dashboard
- THEN the widget MUST attempt the OR fetch again
- AND MUST render OR data if the fetch succeeds

---

### Requirement: OR requests use session credentials (REQ-OR-006)

All runtime OR requests MUST use `@nextcloud/axios` (which includes the
CSRF token and session cookie automatically). Widgets MUST NOT pass
hardcoded tokens, API keys, or forge request headers.

#### Scenario: Widget uses @nextcloud/axios

- GIVEN an OR-data widget that needs to fetch objects
- WHEN it makes the HTTP request
- THEN the import MUST be `import axios from '@nextcloud/axios'`
- AND the request MUST NOT include a custom `Authorization` header
- AND it MUST NOT bypass CSRF protection

---

## Implementation notes

- `useOrFeatureDetect()` lives at
  `src/composables/useOrFeatureDetect.js`. See
  `docs/widgets/or-data.md` for the canonical widget pattern.
- The `dependencies: []` guard in `scripts/check-manifest.js` blocks
  accidental re-introduction of OR as a hard dep (runs in
  `npm run check:manifest`, which is wired into `npm run lint`).
