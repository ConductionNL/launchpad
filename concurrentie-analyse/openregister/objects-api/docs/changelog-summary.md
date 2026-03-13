# Objects API — Changelog Summary (Key Versions)

## v3.6.0 (2026-02-06)
- Add ObjectType fields & ObjectTypeVersion model (preparing for v4.0.0 merge)
- Add `import_objecttypes` command for migration from Objecttypes API
- Support Open Archiefbeheer destruction: `references` field on ObjectRecord
- Experimental cloud events: zaak-gekoppeld / zaak-ontkoppeld
- Remove `linkable_to_zaken` from ObjectType
- Upgrade to Django 5.2.11, Python dependencies

## v3.5.0 (2025-12-01)
- OpenTelemetry metrics for HTTP requests, users, logins, CRUD operations
- OIDC refactored: separate OIDCProvider and OIDCClient configs
- CSV export option for dump_data.sh script
- Admin search improvement: key__operator__value patterns for JSON data
- Upgrade to mozilla-django-oidc-db 1.1.1

## v3.4.0 (2025-10-28)
- Data migration denormalizing object_type on ObjectRecord (40min-1.5hr)
- Performance improvement: avoids JOINs

## v3.3.0-3.3.1 (2025-10-02/16)
- Bug fixes and maintenance

## v3.2.0 (2025-09-16)
- Maintenance release

## v3.1.x (2025-05-26 through 2025-08-04)
- Multiple patch releases

## v3.0.0 (2025-01-22)
- Major version release
- Breaking changes from v2.x

## v2.5.0 (2025-01-09)
- Last v2.x release

## Key Trends
1. **Merging Objecttypes into Objects API** (v4.0.0) — simplifying from 2 apps to 1
2. **OpenTelemetry adoption** — modern observability stack
3. **Open Archiefbeheer integration** — archive/destruction workflows
4. **Active development** — new release every 2 months
5. **PostgreSQL 14+ requirement** — dropped older versions
