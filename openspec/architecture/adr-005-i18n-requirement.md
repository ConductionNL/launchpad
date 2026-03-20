# ADR-005: Internationalization — Dutch and English Required

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, tasks
**Last updated:** 2026-03-19

## Context

Conduction apps serve Dutch government organizations (primary market) but are also used internationally and published as open source. User-facing text that exists only in Dutch excludes international contributors and users. Text that exists only in English excludes Dutch civil servants with limited English proficiency.

## Decision

- All user-facing text MUST be translatable using Nextcloud's `l10n` / `t()` translation system (PHP: `$this->l->t()`, JS: `t(appName, 'key')`).
- Apps MUST provide translations for at minimum Dutch (nl) and English (en).
- Hardcoded user-facing strings in templates, Vue components, or API error messages are NOT allowed.
- Translation files MUST be maintained in `l10n/` (PHP apps) or the app's translation mechanism.
- Date, time, number, and currency formatting MUST respect the user's locale settings.
- API error messages SHOULD be in English by default but MAY support localized responses via `Accept-Language` header.

## Consequences

- Spec scenarios involving user-facing messages MUST use translation key references or describe the message intent, not hardcoded strings.
- Tasks introducing new UI text MUST include translation key creation for both nl and en.
- The i18n shared spec (`openspec/specs/i18n-*/spec.md`) contains detailed requirements per app.

## Exceptions

- Developer-facing log messages and debug output MAY be English-only.
- API field names and JSON keys MUST be English (these are technical identifiers, not user-facing text).
- Documentation (README, ARCHITECTURE.md) MAY be English-only.
