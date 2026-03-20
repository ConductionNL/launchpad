# ADR-012: Frontend Architecture Patterns

**Status:** accepted
**Scope:** company-wide
**Applies to:** design, tasks
**Last updated:** 2026-03-19

## Context

All Conduction Nextcloud apps use Vue 2.7 with Pinia for state management and `@nextcloud/vue` as the primary component library. Inconsistent frontend patterns across apps increase onboarding time, make cross-app code sharing difficult, and lead to bugs when developers switch between projects.

The shared `@conduction/nextcloud-vue` library provides higher-level components (CnIndexPage, CnDataTable, CnFormDialog, etc.) and a generic Pinia store for OpenRegister CRUD operations. Apps should leverage these shared components rather than building custom equivalents.

## Decision

### State Management
- Apps MUST use Pinia for all state management.
- Stores MUST be organized in `src/store/modules/{resource}.js` with a barrel export in `src/store/store.js`.
- Apps SHOULD use `createObjectStore` from `@conduction/nextcloud-vue` for OpenRegister CRUD operations rather than building custom stores.
- Apps MUST NOT use Vuex (legacy) or custom reactive state patterns.

### Component Library
- Apps MUST use `@nextcloud/vue` components as the primary UI library.
- Apps SHOULD use `@conduction/nextcloud-vue` components (CnIndexPage, CnDataTable, etc.) for standard list/detail/form patterns.
- Before building a custom component, developers MUST check `@conduction/nextcloud-vue` for an existing equivalent.
- Custom components that could benefit other apps SHOULD be proposed as additions to the shared library.

### Vue Conventions
- Apps MUST use Vue 2 Options API (Composition API is not supported in the current stack).
- All `<style>` blocks MUST use the `scoped` attribute.
- Components MUST use `@nextcloud/l10n` `t()` function for all user-facing strings.
- CSS MUST use Nextcloud CSS variables (`--color-primary-element`, `--color-border`, etc.) — never reference `--nldesign-*` directly (the nldesign app overrides Nextcloud's variables automatically).

### Directory Structure
- Components: `src/components/{Feature}/`
- Views (routed pages): `src/views/{feature}/`
- Entities/types: `src/entities/{type}/`
- Store modules: `src/store/modules/`
- Router: `src/router/`

## Consequences

- Design documents for UI features MUST specify which shared components are used.
- Tasks MUST NOT include building custom table, pagination, or form components when shared equivalents exist.
- New shared components MUST be added to `@conduction/nextcloud-vue`, not duplicated across apps.

## Exceptions

- `nldesign` uses vanilla PHP templates and vanilla JS (no Vue) — frontend patterns do not apply.
- `nextcloud-vue` itself defines the shared patterns — it follows its own internal conventions (see its CLAUDE.md).
- `larpingapp` uses TypeScript entity classes with Zod validation, which is a more advanced pattern that other apps MAY adopt.
