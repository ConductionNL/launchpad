# ADR-003: The embed theme classes are an ADR-022 documented exception

- **Status:** Accepted (Ruben van der Linde, 23 Nov 2026)
- **Date:** 2026-11-23
- **Sunset:** 2027-06-30
- **Gate 23 rules:** 4
- **Deciders:** Ruben van der Linde
- **Scope:** launchpad, the themes an embedded dashboard or widget takes from the website that embeds it
- **References:** hydra ADR-022 (apps consume OpenRegister abstractions), exception clause. Hydra gate 23, `or-abstraction-anti-patterns`, rule 4 (`consume-or-tenant-fleet-wide`). Capability `embedded-analytics`, REQ-EMB-005. ADR-002 of this repository, the same mechanism for rule 6.

## Context

ADR-022 gives tenancy (organisations, tenant boundaries, tenant lifecycle) to
OpenRegister. Gate 23 rule 4 enforces that on file name: any `Tenant*.php`
outside OpenRegister. Four launchpad files match:

- `lib/Entity/TenantTheme.php`
- `lib/Service/Embed/TenantThemeService.php`
- `lib/Service/Embed/TenantThemeValidator.php`
- `lib/Controller/TenantThemeApiController.php`

None of them is tenancy. A "tenant theme" is the brand of a website that embeds
a launchpad dashboard or widget (REQ-EMB-005): CSS variables, a logo and
`:root`-only CSS that an embed token points at. It decides how an embed looks,
never who may see what, and it isolates nothing. Access stays with the embed
token (REQ-EMB-001 to 004) and Nextcloud's own users.

The themes are already stored the way ADR-022 asks: as OpenRegister objects of
the schema `tenant_theme` in launchpad's register, read and written through
OpenRegister's `ObjectService` by `lib/Service/Embed/EmbedObjectStore.php`. No
launchpad table, middleware or schema switch holds them.

The only thing rule 4 sees is the word "Tenant" in the file names. Renaming the
classes would change no behaviour, while the API path `/api/tenant-themes`, the
token field `tenantThemeId` and the stored schema slug `tenant_theme` would keep
the word; the schema slug is stored data and does not change.

## Decision

Per the ADR-022 exception clause, gate 23 suppresses these four paths under rule
4, the tenant class name rule, and under no other rule. The `Gate 23 rules: 4`
line in the header tells the gate so. Every suppression is printed on every gate
run with this ADR named beside it.

- `lib/Entity/TenantTheme.php`
- `lib/Service/Embed/TenantThemeService.php`
- `lib/Service/Embed/TenantThemeValidator.php`
- `lib/Controller/TenantThemeApiController.php`

## When this ends

The exception holds while an embed theme is a style record and not an
organisation. If embed themes come to belong to OpenRegister organisations (one
theme per organisation that embeds launchpad), the themes move onto the
organisation in the same change and this ADR is withdrawn. The sunset date
forces the question to be asked again by then.
