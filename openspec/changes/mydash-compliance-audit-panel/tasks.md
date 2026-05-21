# Tasks: mydash-compliance-audit-panel

> `kind: config` per ADR-032. Every task is a spec authoring task — no code, no manifest edits, no tests. Per-widget implementation chains follow after this change is archived.

## Spec authoring

### 1. Author `mydash-compliance-audit-panel`

- **spec_ref:** `specs/mydash-compliance-audit-panel/spec.md`
- **files:** `specs/mydash-compliance-audit-panel/spec.md`
- **acceptance:** 9 requirements covering placement registration, manifest entry, content shape, runtime GraphQL contract for OR audit-trail and archival-destruction + shillinq retention rules, compliance evidence surfacing (via OR object-interactions), deadline alerts with cross-session persistence, portfolio filter with server-side scoping, graceful degradation when siblings are absent, and a11y / responsive baseline. Each requirement carries ≥2 GIVEN/WHEN/THEN scenarios; the portfolio filter + deadline alert requirements carry ≥3 scenarios each. Total scenarios: ≥18.
- **check:** `python3 -c "import re; s=open('specs/mydash-compliance-audit-panel/spec.md').read(); assert s.count('### REQ-CAP-') >= 9 and s.count('#### Scenario:') >= 18"` (or equivalent grep)

## Deduplication check

### 2. Verify no overlap with existing mydash specs

- **files affected:** `openspec/specs/`
- **acceptance:** grep for 'compliance', 'audit', 'deadline', 'portfolio', 'framework' across all existing mydash specs (`openspec/specs/*/spec.md`). Document findings in tasks.md as a comment. Expected result: zero overlap. If found, justify in design.md why duplication is necessary.
- **check:** Manual review of grep results. Zero conflicts expected.

## Design review

### 3. Verify design doc completeness

- **files affected:** `design.md`
- **acceptance:** design.md MUST include:
  - **Reuse Analysis section**: lists which existing mydash specs are reused (expected: `widgets`, `widget-add-edit-modal`, `dashboards`, `permissions`, `conditional-visibility`)
  - **Cross-app data contract section**: documents the runtime-only contract per `feedback_mydash-no-or-dependency.md` (soft requires, no install-time deps, empty-state fallback)
  - **Seed Data section**: documents that no seed data is needed (mydash holds no schemas; siblings carry their own)
  - **Declarative-vs-imperative decision section**: explains why this is `kind: config` with no service classes
  - **Spec-sizing decision (ADR-032) section**: confirms this change is `kind: config` end-to-end
- **check:** Manual review of design.md. All five sections present.

## Proposal review

### 4. Verify proposal doc completeness

- **files affected:** `proposal.md`
- **acceptance:** proposal.md MUST include:
  - **Why section**: articulates demand (45 tender mentions for deadline alert, 42 for portfolio filter) + the problem (missing from existing mydash widgets)
  - **What Changes section**: describes the net-new widget + confirms this is `kind: config` (no new services, no new schemas)
  - **Impact section**: itemizes affected docs, code (deferred), APIs (no new endpoints), dependencies (no install-time deps), and trade-offs
  - **Out of scope section**: clarifies what's deferred to the implementation chain
- **check:** Manual review of proposal.md. All four subsections present with substantive content.

## Verification

### 5. Verify all artifacts exist and are linked

- **files affected:** all four OpenSpec artifacts
- **acceptance:** 
  - `proposal.md` exists and is well-formed markdown
  - `design.md` exists and references the proposal and spec
  - `specs/mydash-compliance-audit-panel/spec.md` exists with ≥9 requirements and ≥18 scenarios
  - `tasks.md` exists (this file) with all tasks defined
- **check:** `ls -la openspec/changes/mydash-compliance-audit-panel/` shows all four files. Spot-check: each file has a frontmatter or header identifying its role in the spec.

## Notes

- This change is **spec-only** (`kind: config` per ADR-032). All implementation code (Vue components, manifest entries, GraphQL clients) lands in a follow-up chain once the spec is archived and approved.
- Specter's context brief (context-brief.md) is the authoritative source for feature demand, user stories, stakeholder requirements, and acceptance criteria.
- The two Specter features ("deadline alert", "portfolio filter") both map to req scenarios in REQ-CAP-002, REQ-CAP-005, and REQ-CAP-006, ensuring traceability from context-brief through spec.
- All cross-app dependencies (OR, shillinq, docudesk) are soft (runtime-only) per the `feedback_mydash-no-or-dependency.md` contract. The widget gracefully degrades when any sibling is absent (REQ-CAP-007).

## Deduplication findings

No overlap detected with existing mydash specs. The compliance-audit widget is a **new capability** that:
- Consumes existing mydash infrastructure (`widgets`, `widget-add-edit-modal`, `dashboards`, `permissions`)
- Consumes existing OR abstractions via runtime GraphQL (audit-trail-immutable, archival-destruction-workflow)
- Does NOT duplicate functionality in existing `calendar-widget`, `files-widget`, `admin-roles`, or `role-based-content` specs

This aligns with ADR-022 (apps consume OR abstractions rather than build parallel mechanisms) and ADR-032 (spec-only changes ship as `kind: config` end-to-end).
