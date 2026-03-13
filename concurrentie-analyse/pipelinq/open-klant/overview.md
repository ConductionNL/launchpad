---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Open Klant -- Competitive Analysis Overview

## Product Identity

- **Product**: Open Klant
- **Vendor**: Maykin Media B.V. (Amsterdam)
- **Version**: 2.15.0 (Klantinteracties API 0.6.0, Contactgegevens API 1.1.1)
- **License**: EUPL
- **Repository**: https://github.com/maykinmedia/open-klant
- **Developed with**: Gemeente Amsterdam, Gemeente Den Haag, Gemeente Utrecht, VNG Realisatie

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | Python 3.12+ |
| Framework | Django + Django REST Framework |
| Database | PostgreSQL 17 |
| Cache/Broker | Redis 8 |
| Task Queue | Celery (with Flower monitoring) |
| API Schema | drf-spectacular (OpenAPI 3.x) |
| Auth | Custom token-based (NOT Django's built-in Token model) |
| Admin 2FA | django-two-factor-auth / WebAuthn |
| SSO | Mozilla Django OIDC (Keycloak support) |
| Notifications | notifications-api-common (VNG standard) |
| Cloud Events | Custom (EXPERIMENTAL, for zaak linking) |
| Observability | OpenTelemetry (Prometheus, Grafana, Promtail) |
| Structured Logging | structlog |
| Code Quality | ruff, pre-commit, CodeQL |
| Deployment | Docker, docker-compose, uWSGI |
| API Standard | VNG Klantinteracties API specification |
| Docs | Sphinx / ReadTheDocs |

## Architecture

Open Klant is a **standalone Django application** that serves two API components:

### 1. Klantinteracties API (`/klantinteracties/api/v1/`)
The core API implementing the VNG Klantinteracties standard. This is the main product.

### 2. Contactgegevens API (`/contactgegevens/api/v1/`)
A separate, simpler API for basic person/organisation contact details. Not based on a standard.

### Key Architectural Patterns

- **Polymorphic serializers**: Partij and Actor use a custom `PolymorphicSerializer` that switches sub-serializers based on `soort_partij`/`soort_actor` discriminator fields.
- **GegevensGroepType**: VNG pattern for flattening nested fields at the database level while presenting them as nested objects in the API (bezoekadres, correspondentieadres, contactnaam, actoridentificator, partij_identificator, etc.).
- **Expand mechanism**: Custom `ExpandMixin` + `ExpandJSONRenderer` allows clients to request related objects be included inline via `?expand=betrokkenen,digitale_adressen` query parameter.
- **Notification channels**: Partij and InterneTaak changes can optionally send notifications to a Notificaties API (disabled by default).
- **OpenTelemetry metrics**: Every CRUD operation on every entity has counters tracked.
- **Structured audit logging**: Every create/update/delete operation logs the UUID, related entity UUIDs, and the calling token's identifier + application.

## Database Schema (Entity-Relationship)

```
┌─────────────┐     ┌──────────────────┐     ┌──────────────┐
│   Partij    │────>│  DigitaalAdres   │<────│  Betrokkene  │
│             │     │                  │     │              │
│ soort_partij│     │ soort: email/    │     │ rol: klant/  │
│ nummer      │     │   telefoon/overig│     │   vertegen-  │
│ indicatie_  │     │ adres            │     │   woordiger  │
│  actief     │     │ omschrijving     │     │ initiator    │
│ voorkeurstaal│    │ is_standaard     │     │ contactnaam  │
│ indicatie_  │     │ referentie       │     │ organisatie- │
│  geheim-    │     │ verificatie_datum│     │   naam       │
│  houding    │     └──────────────────┘     │ bezoekadres  │
│ interne_    │                              │ corr.adres   │
│  notitie    │     ┌──────────────────┐     └──────┬───────┘
│ bezoekadres │     │  Klantcontact    │<───────────┘
│ corr.adres  │     │                  │
└──────┬──────┘     │ kanaal           │     ┌──────────────┐
       │            │ onderwerp        │────>│Onderwerpobj. │
       │            │ inhoud           │     │              │
       │            │ taal             │     │ onderwerp-   │
       │            │ vertrouwelijk    │     │  object-     │
       │            │ indicatie_       │     │  identificator│
       │            │  contact_gelukt │     └──────────────┘
       │            │ plaatsgevonden_op│
       │            │ metadata (JSON)  │     ┌──────────────┐
       │            │ referentienummer │────>│   Bijlage    │
       │            └────────┬─────────┘     │              │
       │                     │               │ bijlage-     │
       │                     │               │  identificator│
       │            ┌────────┴─────────┐     └──────────────┘
       │            │  ActorKlantcont. │
       │            │  (link table)    │     ┌──────────────┐
       │            └────────┬─────────┘     │  InterneTaak │
       │                     │               │              │
       │            ┌────────┴─────────┐     │ status:      │
       │            │     Actor        │     │  te_verwerken│
       │            │                  │     │  /verwerkt   │
       │            │ soort_actor:     │     │ gevraagde_   │
       │            │  medewerker/     │────>│  handeling   │
       │            │  geautomatiseerd/│     │ toelichting  │
       │            │  org.eenheid     │     │ afgehandeld_op│
       │            └──────────────────┘     └──────────────┘
       │
       ├──── Persoon (1:1, contactnaam)
       ├──── Organisatie (1:1, naam)
       ├──── Contactpersoon (1:1, contactnaam, werkte_voor_partij->Partij[org])
       │
       ├──── PartijIdentificator (1:N)
       │     │ code_register: brp/hr
       │     │ code_objecttype: natuurlijk_persoon/niet_natuurlijk_persoon/vestiging
       │     │ code_soort_object_id: bsn/kvk_nummer/rsin/vestigingsnummer
       │     │ object_id: actual value
       │     └── sub_identificator_van (self-FK, e.g. vestigingsnummer scoped to kvk)
       │
       ├──── Rekeningnummer (1:N, IBAN+BIC)
       ├──── CategorieRelatie (N:M via junction) ── Categorie (naam)
       └──── Vertegenwoordigden (self-M:N, vertegenwoordigende <-> vertegenwoordigde)
```

## API Surface Summary

### Klantinteracties API Endpoints

All endpoints are at `/klantinteracties/api/v1/` with full CRUD (GET list, GET detail, POST, PUT, PATCH, DELETE):

| Endpoint | Model | Notes |
|----------|-------|-------|
| `/actoren/` | Actor | Polymorphic: medewerker/geautomatiseerd/org.eenheid |
| `/actorklantcontacten/` | ActorKlantcontact | Link table between Actor and Klantcontact |
| `/klantcontacten/` | Klantcontact | Contact moment with metadata JSON field |
| `/betrokkenen/` | Betrokkene | Party involvement in a klantcontact |
| `/onderwerpobjecten/` | Onderwerpobject | Subject of klantcontact (e.g. zaak UUID) |
| `/bijlagen/` | Bijlage | Attachment references for klantcontact |
| `/maak-klantcontact/` | -- | **Composite**: creates Klantcontact + Betrokkene + Onderwerpobject in one POST |
| `/internetaken/` | InterneTaak | Internal tasks assigned to actors |
| `/partijen/` | Partij | Polymorphic: persoon/organisatie/contactpersoon |
| `/partij-identificatoren/` | PartijIdentificator | BRP/HR identifiers for parties |
| `/categorieen/` | Categorie | EXPERIMENTAL: party categories |
| `/categorie-relaties/` | CategorieRelatie | EXPERIMENTAL: party-category associations |
| `/vertegenwoordigingen/` | Vertegenwoordigden | Party representation relationships |
| `/digitaleadressen/` | DigitaalAdres | Email/phone/other digital contact addresses |
| `/rekeningnummers/` | Rekeningnummer | IBAN/BIC bank account numbers |

### Contactgegevens API Endpoints

At `/contactgegevens/api/v1/`:

| Endpoint | Model | Notes |
|----------|-------|-------|
| `/organisatie/` | Organisatie | Basic org contact data with address |
| `/persoon/` | Persoon | Basic person contact data with address |

### Authentication

All API endpoints use **token-based authentication**:
- Token is a 40-character string stored in `TokenAuth` model
- Sent as `Authorization: Token <token>` header
- Each token has: identifier (slug), contact_person, email, organization, application, administration
- No RBAC -- any valid token grants full read/write access to all endpoints

### Key Query Features

- **Expand** (`?expand=`): Inline related objects. Supports 2 levels (e.g. `betrokkenen.had_klantcontact`)
- **Filtering**: Extensive filter sets on nearly every field, including deep relation filters (e.g. `had_betrokkene__was_partij__partij_identificator__object_id`)
- **URL-based filtering**: Can filter by related object URL in addition to UUID
- **Pagination**: Dynamic page size via `DynamicPageSizePagination`

## Deployment Architecture

```
                    ┌──────────┐
                    │  Redis   │
                    └─────┬────┘
                          │
    ┌─────────┐     ┌─────┴────┐     ┌──────────┐
    │ Postgres│<────│   Web    │────>│  Celery   │
    │   17    │     │ (uWSGI)  │     │  Worker   │
    └─────────┘     └──────────┘     └──────────┘
                          │
                    ┌─────┴────┐
                    │  Celery  │
                    │  Flower  │
                    └──────────┘
```

Optional add-ons:
- Keycloak (OIDC)
- Referentielijsten API (for kanaal validation)
- OpenTelemetry collector (Prometheus, Grafana, Promtail)

## Comparison with Pipelinq

### What Open Klant Has That Pipelinq Should Consider

1. **VNG Klantinteracties API standard compliance** -- This is the Dutch government standard for client interaction tracking. Any municipality implementing Common Ground will expect this API.

2. **Comprehensive contact interaction tracking** (Klantcontact/Betrokkene model) -- Records WHO contacted, WHEN, via WHAT channel, about WHAT subject, with WHAT outcome.

3. **Polymorphic party model** (Persoon/Organisatie/Contactpersoon) -- Clean type hierarchy with discriminator pattern.

4. **BRP/HR identification integration** (PartijIdentificator) -- Links parties to BSN (BRP), KvK number, RSIN, Vestigingsnummer with proper validation (11-proof, length checks).

5. **Internal task management** (InterneTaak) -- Creates follow-up tasks from client contacts, assigned to actors (employees, automated systems, org units).

6. **Zaak integration via CloudEvents** -- When an Onderwerpobject references a zaak, it emits `nl.overheid.zaken.zaak-gekoppeld` / `zaak-ontkoppeld` cloud events.

7. **Representation tracking** (Vertegenwoordigden) -- Models which party represents which other party.

8. **Bank account management** (Rekeningnummer) -- IBAN/BIC tracking per party with validation.

9. **Digital address management** with default address per type and verification dates.

10. **Comprehensive audit logging** with structured log events and OpenTelemetry metrics.

11. **Composite endpoint** (`maak-klantcontact`) -- Creates Klantcontact + Betrokkene + Onderwerpobject in a single atomic request.

### What Pipelinq Already Has

- Client/contact management (though via OpenRegister objects, not VNG standard)
- Person/organisation distinction
- Contact information (email, phone)
- Pipeline-based case management (more advanced than Open Klant's simple InterneTaak)

### Critical Gaps for Pipelinq

1. **No VNG Klantinteracties API compliance** -- This is the biggest gap. Municipalities expect this standard.
2. **No BRP/KvK identifier linking** -- No BSN, KvK, RSIN, vestigingsnummer validation and storage.
3. **No contact interaction logging** -- No structured recording of client contacts with channel, subject, content, outcome.
4. **No internal task creation from contacts** -- No workflow from client contact to assigned task.
5. **No zaak integration via CloudEvents** -- No event-driven zaak linking.
6. **No representation/delegation tracking** -- No modeling of who represents whom.
7. **No bank account management** -- No IBAN/BIC tracking.
8. **No notification channel integration** -- No VNG Notificaties API support.
