# ADR-009: Mandatory Test Coverage

**Status:** accepted
**Scope:** company-wide
**Applies to:** tasks
**Last updated:** 2026-03-19

## Context

Conduction apps serve Dutch government organizations where reliability is critical. Untested features lead to regressions, broken APIs, and failed tender compliance. Currently, backend unit tests exist across all apps but frontend Jest tests have near-zero coverage, and only 2 of 10 apps have Newman API test collections. Browser-based acceptance tests are ad-hoc.

A feature without tests is a feature that will break silently.

## Decision

### Backend Tests (PHPUnit)
- Every new or changed backend feature MUST have corresponding unit tests in `tests/Unit/`.
- Backend test coverage MUST target 75% line and method coverage for new code.
- Tests MUST run inside the Nextcloud container: `docker exec nextcloud php vendor/bin/phpunit`.
- All tests MUST pass as part of `composer check:strict`.

### Frontend Tests (Jest)
- Every new or changed Vue component MUST have a corresponding Jest test in `tests/javascript/`.
- Tests MUST cover: rendering, props, user interactions, and emitted events.
- Frontend tests MUST run via `npm run test`.

### API Tests (Newman/Postman)
- Every new or changed API endpoint MUST have a corresponding Newman collection in `tests/newman/`.
- Collections MUST test: CRUD operations, error responses (400, 401, 404), pagination, and input validation.
- Collections MUST be runnable via: `npx newman run tests/newman/{collection}.json`.
- Collections SHOULD verify NLGov API Design Rules compliance (pagination metadata, error format).

### Browser Test Scenarios
- Every spec scenario (GIVEN/WHEN/THEN) MUST have a corresponding browser test scenario documented in the tasks.
- Browser tests are executed via the Playwright MCP browser pool during `/opsx:verify` and `/opsx:team-qa`.
- Browser tests MUST verify: UI rendering, user flows, console errors, and network failures.

## Consequences

- Task lists MUST include a "Tests" section with subtasks for each test type (PHPUnit, Jest, Newman, browser).
- A task section MAY be marked "N/A" with justification if genuinely not applicable (e.g., no API endpoints means no Newman tests; no UI means no Jest tests).
- `/opsx:verify` MUST check that test tasks are complete before allowing archive.

## Exceptions

- `nldesign` has no backend logic or API endpoints — only browser tests for theme application are required.
- `nextcloud-vue` is a library — Jest component tests are required, but Newman and browser tests are not (consumers test integration).
- Hotfix changes that fix a critical production bug MAY defer test writing to a follow-up change, but MUST create a tracking task.
