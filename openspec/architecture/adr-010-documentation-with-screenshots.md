# ADR-010: Documentation with Screenshots

**Status:** accepted
**Scope:** company-wide
**Applies to:** tasks
**Last updated:** 2026-03-19

## Context

Conduction apps are evaluated by government procurement teams, tender reviewers, and new developers who need to understand what a feature does before reading code. Features that exist only in code are invisible to non-technical stakeholders. Screenshots provide evidence that a feature works and help users understand workflows.

6 of 10 Conduction apps have adopted Docusaurus for documentation. A consistent documentation standard ensures every feature is discoverable.

## Decision

### Feature Documentation
- Every user-facing feature MUST have documentation in the app's `docs/` directory.
- Documentation MUST include: purpose, how to use it, and expected behavior.
- Documentation MUST be written in English. Dutch translation is RECOMMENDED.
- Documentation MUST be updated when feature behavior changes.

### Screenshots
- Every user-facing feature MUST include at least one screenshot showing the feature in the running application.
- Screenshots MUST be taken from the actual app (not mockups or wireframes).
- Screenshots MUST be stored in `docs/` alongside the feature documentation (e.g., `docs/features/{feature}/` or `docs/screenshots/`).
- Screenshots SHOULD show realistic data, not empty states (unless documenting the empty state itself).

### Documentation Structure
- Apps using Docusaurus SHOULD follow the standard structure: `docs/features/`, `docs/technical/`, `docs/integrations/`.
- Apps MUST maintain a docs index or sidebar navigation that includes all documented features.
- Documentation for removed features MUST be removed or marked as deprecated.

## Consequences

- Task lists MUST include a "Documentation" section with subtasks for writing docs and capturing screenshots.
- `/opsx:apply` captures screenshots using the Playwright MCP browser pool (`browser_take_screenshot`).
- `/opsx:verify` checks that documentation tasks are complete.

## Exceptions

- `nextcloud-vue` is a component library — Storybook or JSDoc component docs are acceptable instead of screenshot-based docs.
- Pure backend changes with no UI impact (e.g., performance optimizations, internal refactoring) MAY skip screenshots but MUST still document the change in technical docs.
- API-only features MAY use request/response examples instead of screenshots.
