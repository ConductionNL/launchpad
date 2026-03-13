# Open Product -- Versioning & Release History

## Versioning Policy

- New version releases every **two months**, at the start of the month
- Major releases every **two years**
- Each major version supported for **24 months** after the next major release
- Only the **two most recent minor versions** are actively supported
- Older minor versions supported for at most **6 months** after their release

## Release History

| Version | Release Date | Supported Until | Key Features |
|---------|-------------|-----------------|-------------|
| **1.6.0** | 2026-02-06 | Current | Eigenaar URN field, thema/subthema content, direct_url for actions, notifications disabled by default |
| **1.5.0** | 2025-12-04 | 2026-06-04 | Doelgroep field, object permissions for Producten API, OIDC config split, OpenTelemetry, URN/URL fields refactor, CSV data dump, Node.js 24 |
| **1.4.0** | 2025-10-13 | 2026-02-06 | CSV export, publicatie dates, aanvraag zaak, location/org filters, structlog exceptions |
| **1.3.0** | 2025-07-14 | 2025-12-04 | Theme filters, `in_aanvraag` status, structlog, pagination keys to English |
| **1.2.0** | 2025-06-13 | 2025-10-13 | Product taken & zaken, Django 5.2 (requires PostgreSQL 14+), dark/light admin theme |
| **1.1.0** | 2025-05-09 | 2025-07-14 | Minor additions |
| **1.0.0** | 2025-04-08 | 2025-06-13 | Initial release |

## Notable Breaking Changes

### v1.5.0
- OIDC configuration format changed: `mozilla-django-oidc-db` updated to 1.1.0, requiring split into `OIDCProvider` and `OIDCClient` models
- Object-level permissions added to Producten API (non-superusers now need explicit producttype permissions)

### v1.4.0
- Default `UWSGI_THREADS` changed to 4

### v1.2.0
- Django 5.2 requires PostgreSQL 14 or higher (PostgreSQL < 14 will fail)

## Development Metrics

- **Repository created:** 2024-11-25
- **First release:** 2025-04-08 (5 months development)
- **Total commits:** ~626 on master
- **Open issues:** ~23
- **Contributors:** Maykin B.V. team
- **GitHub stars:** 3
- **Forks:** 0

## Dependency Upgrades (v1.6.0)

- Django 5.2.11
- open-api-framework 0.13.4
- commonground-api-common 2.10.7
- notifications-api-common 0.10.1
- zgw-consumers 1.2.0
- mozilla-django-oidc-db 1.1.1
