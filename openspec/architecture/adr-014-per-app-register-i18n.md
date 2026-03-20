# ADR-014: Per-App Register Content i18n Adoption

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, tasks
**Last updated:** 2026-03-20

## Context

ADR-005 covers app UI translations (l10n/en.json, l10n/nl.json). But apps also need multi-language support for register content (e.g., product descriptions, template titles, case type names). OpenRegister provides the infrastructure (translatable flag, language-keyed JSON, Accept-Language negotiation). Each app must specify which of its schema fields are translatable and how the i18n system applies to its domain.

## Decision

- Each app that uses OpenRegister for data storage MUST have a `register-i18n` spec in its openspec/specs/ directory.
- The app's register-i18n spec MUST reference OpenRegister's register-i18n spec as the foundation.
- The spec MUST list which schema properties are translatable vs single-value.
- The spec MUST define app-specific terminology mappings (e.g., Pipelinq: klant/client, verzoek/request).
- The spec MUST specify which languages are supported beyond the ADR-005 minimum (nl/en).
- Generated/computed content (e.g., DocuDesk generated documents) MAY be exempt from translation.
- The spec SHOULD define translation completeness requirements per entity type.

## Consequences

- Task lists MUST include register-i18n tasks.
- Specs for new schemas MUST declare translatable fields.

## Exceptions

- nldesign — CSS tokens are language-independent.
- nextcloud-vue — components accept pre-translated props.
