# Architectural Design Rules (ADRs)

Architectural Design Rules define **constraints that all OpenSpec specifications must comply with**. They are enforced during artifact creation (`/opsx:ff`, `/opsx:continue`) and verified during review (`/opsx:verify`).

## Structure

ADRs exist at two levels:

- **Company-wide** (`openspec/architecture/`) — Apply to ALL Conduction apps
- **Repo-specific** (`{app}/openspec/architecture/`) — Apply only to that app; can extend or override company-wide rules

## ADR File Format

```markdown
# ADR-NNN: Title

**Status:** accepted | proposed | deprecated | superseded by ADR-NNN
**Scope:** company-wide | {app-name}
**Applies to:** specs | design | tasks | all
**Last updated:** YYYY-MM-DD

## Context

Why this rule exists. What problem it prevents.

## Decision

The rule itself, using RFC 2119 keywords (MUST, SHALL, SHOULD, MAY).

## Consequences

What this means for spec authors and implementers.

## Exceptions

Known cases where this rule does not apply, with justification.
```

## How Enforcement Works

1. **During artifact creation** — When `/opsx:ff` or `/opsx:continue` generates specs, design, or tasks, it reads all applicable ADRs and validates the output against them. Violations are flagged before the artifact is written.

2. **During verification** — `/opsx:verify` includes an "ADR Compliance" dimension in its report, checking each spec requirement against applicable architectural rules.

3. **Override mechanism** — A repo-specific ADR can override a company-wide ADR by referencing it: `Overrides: ADR-NNN`. The override MUST include justification.

## Naming Convention

- Company-wide: `adr-NNN-short-description.md` (e.g., `adr-001-openregister-data-layer.md`)
- Repo-specific: Same format, numbered independently per repo

## Current ADRs

| ADR | Title | Applies to | Scope |
|-----|-------|-----------|-------|
| 001 | OpenRegister as Universal Data Layer | specs, design | company-wide |
| 002 | REST API Conventions | specs, design | company-wide |
| 003 | NL Design System for All UI | specs, design, tasks | company-wide |
| 004 | Nextcloud App Framework Patterns | specs, design, tasks | company-wide |
| 005 | Internationalization — Dutch and English Required | specs, tasks | company-wide |
| 006 | OpenRegister Schema Standards | specs, design | company-wide |
| 007 | Security and Authentication | specs, design, tasks | company-wide |
| 008 | Backend Layering — Controller → Service → Mapper | design, tasks | company-wide |
| 009 | Mandatory Test Coverage | tasks | company-wide |
| 010 | Documentation with Screenshots | tasks | company-wide |
| 011 | Deduplication Check Against OpenRegister Core | specs, design, tasks | company-wide |
| 012 | Frontend Architecture Patterns | design, tasks | company-wide |
| 013 | Loadable Register Templates | specs, design, tasks | company-wide |
| 014 | Per-App Register Content i18n Adoption | specs, tasks | company-wide |
| 015 | Per-App Prometheus Metrics and Health Checks | specs, design, tasks | company-wide |

### Repo-Specific ADRs

| ADR | Title | App |
|-----|-------|-----|
| 001 | International First, Dutch Mapping | pipelinq |

## Adding a New ADR

1. Choose the next available number
2. Use the template above
3. Set status to `proposed`
4. Get team review
5. Change status to `accepted`
