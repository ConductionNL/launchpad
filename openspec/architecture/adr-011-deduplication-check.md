# ADR-011: Deduplication Check Against OpenRegister Core

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-19

## Context

OpenRegister is the foundation repository that provides shared services used by all Conduction apps: ObjectService, RegisterService, SchemaService, ConfigurationService, TextExtractionService, FileService, and more. Apps that duplicate OpenRegister functionality create maintenance burden, inconsistent behavior, and missed bug fixes when the core service is updated.

Historical examples: apps reimplementing pagination logic that ObjectService already provides, apps creating custom search handlers when OpenRegister's faceted search covers the use case, apps building file upload handling that FileService already supports.

## Decision

### Proposal Phase
- Before proposing a new capability, authors MUST search existing OpenRegister specs (`openspec/specs/`) and services (`openregister/lib/Service/`) for overlap.
- If a similar capability exists in OpenRegister, the proposal MUST reference it and explain why new code is needed rather than extending the existing capability.
- Proposals that duplicate existing functionality without justification MUST be rejected.

### Design Phase
- Design documents MUST include a "Reuse Analysis" section listing which existing OpenRegister services are leveraged.
- If the design introduces logic that could be useful to other apps, it SHOULD propose adding it to OpenRegister core rather than implementing it in the app.

### Task Phase
- Task lists MUST include a "Deduplication Check" task that verifies no overlap with:
  - OpenRegister services (ObjectService, RegisterService, SchemaService, ConfigurationService)
  - Existing shared specs (`openspec/specs/`)
  - Shared Vue components (`@conduction/nextcloud-vue`)
- The deduplication task MUST document findings (even if "no overlap found").

## Consequences

- Spec authors must be familiar with OpenRegister's service catalog before writing specs.
- `/opsx:ff` and `/opsx:continue` MUST check for overlap during proposal and spec generation.
- `/opsx:verify` MUST verify that the deduplication check task was completed.

## Exceptions

- OpenRegister itself is exempt from checking against itself — but MUST check for internal duplication between its own services.
- `nldesign` operates at the CSS layer with no OpenRegister service overlap — deduplication check focuses on existing token sets and CSS layers instead.
- `nextcloud-vue` checks against its own existing components and utilities rather than OpenRegister services.
