# Tasks: add-launchpad-discovered-and-spend-analytics

> `kind: config` per ADR-032. Every task is a spec authoring task —
> no code, no manifest edits, no tests. Per-widget implementation
> chains follow after this change is archived.

## Spec authoring

### 1. Author `launchpad-spend-analytics-widget` (consolidated)

- **spec_ref:** `specs/launchpad-spend-analytics-widget/spec.md`
- **files:** `specs/launchpad-spend-analytics-widget/spec.md`
- **acceptance:** 8 requirements covering placement registration,
  manifest entry, content shape, runtime GraphQL contract for
  financeq + procest, AI-insights surfacing (consumed via
  openconnector LLM source), drill-through behaviour, evidence
  document attachment via OR object-interactions, empty-state
  contract, and a11y / responsive baseline. Each requirement carries
  ≥1 GIVEN/WHEN/THEN scenario; the runtime GraphQL contract
  requirement carries ≥3 scenarios.
- **check:** `python3 -c "import re; s=open('specs/launchpad-spend-analytics-widget/spec.md').read(); assert s.count('### REQ-SAW-') >= 8 and s.count('#### Scenario:') >= 14"` (or equivalent grep)

### 2. Author `launchpad-ai-dashboard-assistant`

- **spec_ref:** `specs/launchpad-ai-dashboard-assistant/spec.md`
- **files:** `specs/launchpad-ai-dashboard-assistant/spec.md`
- **acceptance:** ≥6 requirements covering placement registration,
  manifest entry, content shape (model alias + system prompt slot),
  openconnector LLM source contract (no direct Ollama call from
  launchpad), streamed reply rendering, tool-call surfacing
  (read-only over OR objects via OR's MCP discovery per ADR-022),
  empty-state when the LLM source is absent.
- **check:** scenarios ≥10; no mention of Ollama/Qwen client code in
  launchpad.

### 3. Author `launchpad-compliance-audit-panel`

- **spec_ref:** `specs/launchpad-compliance-audit-panel/spec.md`
- **files:** `specs/launchpad-compliance-audit-panel/spec.md`
- **acceptance:** ≥6 requirements covering placement registration,
  BIO/ISO/AVG posture cards, audit-trail summary card consuming
  OR's audit-trail-immutable abstraction, retention disposition
  counts consuming OR's archival-destruction workflow + shillinq's
  retention rules, deadline alerts with persistence across
  sessions, portfolio filter, empty-state.
- **check:** scenarios ≥10; references ADR-022 audit-trail + archival
  rows.

### 4. Author `launchpad-file-access-widget`

- **spec_ref:** `specs/launchpad-file-access-widget/spec.md`
- **files:** `specs/launchpad-file-access-widget/spec.md`
- **acceptance:** ≥5 requirements covering placement registration,
  Nextcloud Files OCS/DAV contract (read-only), dossier-document
  surface (links to OR object-interactions files), per-viewer ACL
  enforcement by the Files API, document-viewer hand-off, access-denied
  empty state.
- **check:** scenarios ≥9; no launchpad file storage.

### 5. Author `launchpad-enterprise-security-access`

- **spec_ref:** `specs/launchpad-enterprise-security-access/spec.md`
- **files:** `specs/launchpad-enterprise-security-access/spec.md`
- **acceptance:** ≥6 requirements covering placement registration,
  read-only role assignment overview (consuming OR's RBAC GraphQL),
  SSO status card (consuming Nextcloud user_saml provider info),
  MFA enforcement status card (consuming NC TOTP/WebAuthn provider
  state), "view as another role" preview (read-only, audit-logged
  via OR audit-trail), revocation reflection, empty-state.
- **check:** scenarios ≥10; references ADR-022 RBAC row.

### 6. Author `launchpad-meeting-calendar-actions`

- **spec_ref:** `specs/launchpad-meeting-calendar-actions/spec.md`
- **files:** `specs/launchpad-meeting-calendar-actions/spec.md`
- **acceptance:** ≥6 requirements covering placement registration,
  Nextcloud Calendar DAV consumption, decidesk meeting/agenda
  GraphQL consumption, agenda-item annotation contract, edit/remove
  annotation flow, empty-state, time-window filter.
- **check:** scenarios ≥10; no launchpad-local calendar storage.

### 7. Author `launchpad-mobile-remote-access`

- **spec_ref:** `specs/launchpad-mobile-remote-access/spec.md`
- **files:** `specs/launchpad-mobile-remote-access/spec.md`
- **acceptance:** ≥6 requirements covering manifest mobile-readiness
  declaration on widget entries, responsive-fallback contract
  (widgets that omit the declaration MUST be hidden on the mobile
  breakpoint), session timeout reflection on the workspace shell,
  touch-control affordances on every widget (delegated to
  `responsive-grid-breakpoints`), curated-app + news surface gating
  by role per `role-based-content`, empty-state on mobile when
  zero widgets declare mobile-ready.
- **check:** scenarios ≥10; declarative-only (no service-worker /
  PWA implementation surface in scope).

## Deduplication check

### 8. Verify no overlap with existing launchpad specs

- **spec_ref:** all 7 new specs
- **files:** read-only — confirms each new spec slug does not
  duplicate existing launchpad specs (`widgets`, `calendar-widget`,
  `files-widget`, `tiles`, `permissions`, `admin-roles`,
  `role-based-content`, `responsive-grid-breakpoints`, etc.).
- **acceptance:** every new spec's purpose paragraph explicitly
  names the existing specs it extends/consumes and the boundary
  is one-sentence stated. No requirement in the new specs
  redefines a requirement already covered by an existing spec
  (e.g. a new spec MUST NOT redefine grid placement — that's
  `widget-collision-placement`).
- **check:** for each new spec slug, `grep -l "Reuses (launchpad)" specs/<slug>/spec.md` returns 1.

## Cross-spec consistency

### 9. Verify cross-app data contract is uniform

- **spec_ref:** all 7 new specs
- **files:** read-only
- **acceptance:** every spec that consumes sibling-app data carries
  the five-clause runtime contract from design.md → "Cross-app
  data contract" (soft-requires, runtime GraphQL/DAV, empty-state
  contract, no caching beyond component lifetime, sibling-app
  RBAC). No spec adds a hard dep on a sibling app.
- **check:** `grep -L "runtime" specs/launchpad-*/spec.md` returns
  empty (every spec mentions runtime consumption).

### 10. Verify ADR references are correct

- **spec_ref:** all 7 new specs
- **files:** read-only
- **acceptance:** every spec referencing OR abstractions (audit
  trail, RBAC, archival, integration registry, object-interactions)
  cites ADR-022. Every spec referencing manifest registration cites
  ADR-024. Every spec referencing scheduled / lifecycle / aggregation
  behaviour (none should) is flagged for review against ADR-031 —
  expected zero matches because launchpad holds no OR-backed schemas.
- **check:** `grep -L "ADR-022\|ADR-024" specs/launchpad-*/spec.md`
  returns empty.

## Out of scope (defer to per-widget implementation chains)

- Vue component authoring (per-widget renderer + sub-form).
- `src/manifest.json` edits adding the seven `widgets[]` entries.
- `src/constants/widgetRegistry.js` edits.
- Runtime GraphQL operation files.
- Demo dashboard seed entry in `demo-data-showcases`.
- Per-widget Playwright tests, Vitest unit tests.
- Any test or CI task.
