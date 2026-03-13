# OpenZaak Architecture

## Design Principles

Open Zaak focuses on three architectural goals:
1. **Excellent performance** — related objects fetched from database, not over network
2. **Optimal stability** — consolidated service reduces failure points
3. **Data integrity** — database-level constraints ensure consistency

## Consolidated Architecture

Rather than maintaining separate loosely-coupled microservices, Open Zaak combines the "API's voor Zaakgericht werken" into a **single product**. This is a deliberate deviation from the microservice model because these APIs are essentially tightly coupled. Benefits:

- A BESLUIT for a ZAAK does not need to be fetched over the network but directly from the database
- Data integrity enforced at database level (not just service/API level)
- Caching used throughout to prevent redundant network requests

## Components

The architecture consists of:

1. **Registration Component** — the core, exposes all ZGW APIs
2. **Notification Component** — Open Notificaties, required for operation
3. **Selectielijst Component** — exposes the VNG Selectielijst for archiving
4. **Admin Portal** — Django admin interface for catalog and configuration management

## External API Support

Open Zaak fully supports integration with external APIs, even for APIs that Open Zaak itself provides. For example:
- A ZAAK in Open Zaak (via Zaken API) can reference a DOCUMENT from an external Documenten API (different vendor)
- Only requirement: all APIs must adhere to VNG standards

When relations are created to external APIs, data integrity cannot be guaranteed at database level — it is enforced at service level as much as possible.

## Common Ground Compliance

Per Common Ground principles: **no permanent copies are made of original sources in Open Zaak**. Data is always fetched from the source when external references are involved.

## Technology Stack

- **Language:** Python 3.12
- **Framework:** Django
- **Database:** PostgreSQL 14+ with PostGIS extension
- **Cache:** Redis
- **Task Queue:** Celery (with Redis backend)
- **Application Server:** uWSGI
- **Deployment:** Docker containers, Kubernetes-ready
- **Document Storage:** Filesystem (default), Azure Blob Storage, or S3
