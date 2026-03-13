# Objects API — Ecosystem and Integrations

## Common Ground Ecosystem

The Objects API is part of the Dutch Common Ground ecosystem for municipalities. It integrates with:

### Direct Integrations

1. **Objecttypes API** (being merged into Objects API in v4.0.0)
   - Provides JSON schema definitions that Objects API validates against
   - Objects API connects to Objecttypes API via service configuration

2. **Open Notificaties** (Notificaties API)
   - Objects API publishes notifications on `objecten` channel
   - Create/update/delete events
   - Other apps can subscribe to object change events

3. **Open Zaak** (Autorisaties API)
   - API authorization configuration
   - ZGW client_id + secret authentication

4. **Open Archiefbeheer**
   - Support for destruction workflows
   - Objects can reference zaken via `references` field
   - Cloud events: zaak-gekoppeld / zaak-ontkoppeld (experimental)

### Broader Ecosystem

5. **Open Formulieren** — Form submissions can create objects
6. **NLX** — Secure data exchange between organizations
7. **Keycloak / OIDC** — Admin SSO authentication

## Who Uses Objects API?

### Known Municipal Users (Initiators)
- **Municipality of Utrecht** (project lead)
- Municipality of Amsterdam
- Municipality of Delft
- Municipality of Haarlem
- Municipality of Rotterdam
- GBI (Gemeentelijke Basisprocessen Inkomen)

### Community
- Common Ground community group: https://commonground.nl/groups/view/54477963/objecten-en-objecttypen-api

## Common Ground Marketplace

The Objects API is listed as a Common Ground component, tagged with `commonground` topic on GitHub.

## Example Object Types

The documentation includes examples of real-world object types:
1. **Boom (Tree)** — Based on GGM/IMBOR/BGT/IMGeo models
2. **Melding (Report)** — Incident/report registration
3. **Vordering (Claim)** — Financial claim/demand

## Technology Stack

- **Backend**: Python 3.12+ / Django
- **Database**: PostgreSQL 14+ with PostGIS
- **Cache/Queue**: Redis 5/6/7
- **Task Queue**: Celery
- **Web Server**: uwsgi
- **Monitoring**: OpenTelemetry, Elastic APM, Sentry
- **Container**: Docker (OCI images)
- **Orchestration**: Kubernetes with Helm charts

## Performance

Benchmark results (v2.0.0-alpha, 500 objects, 4 objecttypes, single user, 5 minutes):

| Test | Response Time |
|------|--------------|
| Retrieve all objects | 127ms |
| Filter by data_attrs | 117ms |
| Filter by date | 129ms |
| Filter by geo coordinates | 127ms |
| Filter by registrationDate | 130ms |
| Single object retrieve | 106ms |
| **Aggregated** | **123ms** |
