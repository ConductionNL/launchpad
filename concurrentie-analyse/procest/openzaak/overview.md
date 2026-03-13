# OpenZaak Competitive Analysis for Procest

## What is OpenZaak?

OpenZaak is the VNG (Vereniging van Nederlandse Gemeenten) **reference implementation** of the ZGW (Zaakgericht Werken) API standards. It is the de facto standard for case management in Dutch government. Built with Django/DRF, PostgreSQL, and backed by the Dimpact cooperative (130+ municipalities), it implements 5 API components that together form the complete zaakgericht werken platform.

**Repository**: https://github.com/open-zaak/open-zaak
**License**: EUPL-1.2
**Tech stack**: Python 3, Django 4, Django REST Framework, PostgreSQL, Celery, Redis

---

## ZGW API Standard Compliance

OpenZaak implements all 5 ZGW API standards:

| API | Standard | Version | Description |
|-----|----------|---------|-------------|
| Zaken API (ZRC) | VNG ZDS 2.0 | v1 | Case management: create, process, close, archive cases |
| Documenten API (DRC) | VNG ZDS 2.0 | v1 | Document management: versioned documents with locking |
| Catalogi API (ZTC) | VNG ZDS 2.0 | v1 | Type configuration: case types, status types, result types |
| Besluiten API (BRC) | VNG ZDS 2.0 | v1 | Decision management: formal government decisions |
| Autorisaties API (AC) | VNG ZDS 2.0 | v1 | Authorization: JWT-based M2M access control |

Plus integration with:
- **Selectielijst API**: National archive selection list
- **Notificaties API**: Event-driven notifications (webhooks)
- **NLX**: Cross-organisation data exchange

---

## Architecture Overview

```
                    +------------------+
                    |  Catalogi API    |
                    |  (ZTC)           |
                    |  - Catalogus     |
                    |  - ZaakType      |
                    |  - StatusType    |
                    |  - ResultaatType |
                    |  - RolType       |
                    |  - BesluitType   |
                    |  - IOType        |
                    +--------+---------+
                             |
            references       | defines types for
            +-------+--------+--------+--------+
            |                |                  |
    +-------v-------+  +----v--------+  +------v--------+
    |  Zaken API    |  | Documenten  |  | Besluiten API |
    |  (ZRC)        |  | API (DRC)   |  | (BRC)         |
    |  - Zaak       |  | - EIO       |  | - Besluit     |
    |  - Status     |  | - Canonical |  | - BesluitIO   |
    |  - Resultaat  |  | - Versioning|  +------+--------+
    |  - Rol        |  | - Locking   |         |
    |  - ZaakObject |  | - Chunks    |         |
    |  - Eigenschap |  +------+------+  links to both
    +-------+-------+         |         |
            |                 +---------+
            |           cross-references
    +-------v---------+
    | Autorisaties API|
    | (AC)            |
    | - Applicatie    |
    | - Autorisatie   |
    | - CatAutorisatie|
    +-----------------+
```

---

## Component-by-Component Summary

### 1. Catalogi API (ZTC) -- Configuration Backbone
**Spec**: [catalogi-zaaktypen/spec.md](specs/catalogi-zaaktypen/spec.md)

The Catalogi defines ALL types used across the system. Key features:
- **Catalogus**: Top-level container scoped by domein+RSIN
- **ZaakType**: 30+ fields defining case behavior (doorlooptijd, servicenorm, verlenging, opschorting, publicatie)
- **StatusType**: Ordered progression with CheckListItems and notification config
- **ResultaatType**: Archive rules (archiefnominatie, selectielijstklasse, brondatum_archiefprocedure)
- **Concept/Published**: Types start as draft, must be published before use
- **Validity Periods**: Types have begin/einde geldigheid for versioning
- **Auto-sync**: Creating types triggers authorization sync for affected applications

**Django models**: 13 models across 12 files in `components/catalogi/models/`

### 2. Zaken API (ZRC) -- Core Case Management
**Spec**: [zaak-lifecycle/spec.md](specs/zaak-lifecycle/spec.md)

The central component. A Zaak has:
- **Lifecycle**: Create -> Status progression -> Close (eindstatus) -> Archive
- **Identification**: Thread-safe auto-generation using PostgreSQL advisory locks (ZAAK-{year}-{seq10})
- **Suspension/Extension**: Dedicated endpoints with tracking
- **Archiving**: Automatic archiefactiedatum calculation from 9 different brondatum derivation methods
- **Relations**: Deelzaken (sub-cases), relevante zaken, gerelateerde zaken
- **Objects**: 20+ RGBZ object types (addresses, buildings, kadaster, WOZ, etc.)
- **Roles**: 5 betrokkene types with DigiD/eHerkenning authentication context
- **Properties**: Typed zaakeigenschappen with EigenschapSpecificatie

**Django models**: ~50 models across 4 files in `components/zaken/models/`
**API endpoints**: 15+ ViewSets with dedicated lifecycle endpoints

### 3. Documenten API (DRC) -- Document Management
**Spec**: [documenten-api/spec.md](specs/documenten-api/spec.md)

Versioned document management:
- **Canonical + Version**: Identity separated from content for version tracking
- **Locking**: Pessimistic locking with hash-based lock mechanism
- **Chunked uploads**: BestandsDelen for large file uploads
- **Status lifecycle**: in_bewerking -> ter_vaststelling -> definitief -> gearchiveerd
- **Usage rights**: Gebruiksrechten model
- **Dispatch tracking**: Verzending model with full address support
- **Cross-references**: ObjectInformatieObject linking documents to cases/decisions

**Django models**: 8 models in `components/documenten/models.py`

### 4. Besluiten API (BRC) -- Decision Management
**Spec**: [besluiten-api/spec.md](specs/besluiten-api/spec.md)

Formal government decisions:
- **Besluit**: Linked to BesluitType, optionally to a Zaak
- **Cross-API sync**: Creating a Besluit auto-creates ZaakBesluit on the Zaak side
- **Archiving impact**: Changing vervaldatum triggers archive recalculation
- **Document linking**: BesluitInformatieObject with auto-ObjectInformatieObject creation

**Django models**: 2 models in `components/besluiten/models.py`

### 5. Autorisaties API (AC) -- JWT Authorization
**Spec**: [autorisaties-model/spec.md](specs/autorisaties-model/spec.md)

Machine-to-machine authorization:
- **JWT Authentication**: Shared-secret JWT with client_id claim
- **Applicatie**: Client registration with multiple client_ids
- **Type-scoped scopes**: Per zaaktype/informatieobjecttype/besluittype
- **Vertrouwelijkheid filtering**: Max confidentiality level per authorization
- **CatalogusAutorisatie**: Catalogue-wide grants (OpenZaak extension)
- **Superuser mode**: heeft_alle_autorisaties flag

**Django models**: 1 custom model + 2 from vng-api-common

---

## Cross-Cutting Concerns

### Archiving
**Spec**: [archivering/spec.md](specs/archivering/spec.md)
**Business Logic**: [archiving-rules.md](business-logic/archiving-rules.md)

The most complex subsystem. 9 brondatum derivation methods determine when a case dossier should be destroyed or transferred. Integrates with the national Selectielijst API for compliance.

### ETag Concurrency Control
Every major model uses `ETagMixin` for optimistic concurrency. Clients must send `If-Match` headers for updates.

### Audit Trails
Zaken, Documenten, and Besluiten all have full audit trails accessible via nested `/audittrail` endpoints.

### Notifications
Changes to resources trigger notifications to subscribed services via the Notificaties API (webhook-based).

### FkOrServiceUrl (Federated References)
A custom Django field that supports both local foreign keys AND external URL references. This allows OpenZaak instances to reference resources in other OpenZaak deployments.

---

## Total Codebase Metrics

| Component | Models | API Endpoints | Scopes |
|-----------|--------|---------------|--------|
| Catalogi (ZTC) | 13 | 10 ViewSets | 4 |
| Zaken (ZRC) | ~50 | 15+ ViewSets | 7 |
| Documenten (DRC) | 8 | 8+ ViewSets | 6 |
| Besluiten (BRC) | 2 | 4 ViewSets | 4 |
| Autorisaties (AC) | 1+2 | 1 ViewSet | 2 |
| **Total** | **~74** | **38+** | **23** |

---

## Gap Analysis: Procest vs OpenZaak

### What Procest Already Has
- Basic case CRUD via OpenRegister objects
- Flexible schema-based case types
- Document storage via Nextcloud files
- User-based authentication via Nextcloud

### Critical Gaps (Must Have for ZGW compliance)

| # | Gap | OpenZaak Implementation | Effort |
|---|-----|------------------------|--------|
| 1 | **ZaakType configuration** | 13 Catalogi models with concept/published, versioning, validity | Very High |
| 2 | **Status lifecycle** | Ordered StatusTypes, eindstatus detection, auto-close | High |
| 3 | **Archiving** | 9 brondatum methods, selectielijst integration, auto-calculation | Very High |
| 4 | **Authorization model** | JWT M2M, type-scoped scopes, vertrouwelijkheid filtering | High |
| 5 | **Result types** | ResultaatType with archive rules linking | High |
| 6 | **Role system** | 5 betrokkene types, 8 generic roles, DigiD/eHerkenning | High |
| 7 | **Decision management** | Besluit with cross-API sync | Medium |
| 8 | **Document versioning + locking** | Canonical + version model, lock mechanism | Medium |

### Important Gaps (Competitive differentiation)

| # | Gap | OpenZaak Implementation | Effort |
|---|-----|------------------------|--------|
| 9 | Sub-cases (deelzaken) | hoofdzaak/deelzaken hierarchy | Medium |
| 10 | Case relations | RelevanteZaakRelatie, ZaakRelatie | Low |
| 11 | Case suspension/extension | Dedicated endpoints with tracking | Medium |
| 12 | Base registry objects | 20+ RGBZ object type models | Medium |
| 13 | Typed case properties | EigenschapSpecificatie with formaat/lengte | Low |
| 14 | Checklist items | CheckListItem per StatusType | Low |
| 15 | ETag concurrency | ETagMixin on all models | Low |
| 16 | Audit trails | Per-resource audit trail | Medium |
| 17 | Chunked document upload | BestandsDelen model | Medium |
| 18 | Document dispatch tracking | Verzending model | Low |
| 19 | Usage rights | Gebruiksrechten model | Low |
| 20 | Federated references | FkOrServiceUrl for cross-instance | High |

### Procest Advantages Over OpenZaak

| Advantage | Description |
|-----------|-------------|
| **Nextcloud integration** | Built-in collaboration, file management, user management |
| **Simpler deployment** | Single Nextcloud app vs. separate API services |
| **User-friendly UI** | Nextcloud Vue-based UI vs. API-only |
| **Flexible data model** | OpenRegister allows any schema vs. rigid RGBZ models |
| **Modern architecture** | Nextcloud ExApp ecosystem vs. Django monolith |
| **Lower barrier to entry** | No JWT setup, no separate Catalogi configuration |

---

## Recommendations for Procest

1. **Do NOT replicate OpenZaak's model-per-object-type approach** (50+ models). Instead, use OpenRegister's flexible schema system to represent the same types.

2. **DO implement the status progression model** with ordered statuses and eindstatus detection -- this is fundamental to zaakgericht werken.

3. **DO implement archiving basics** (archiefnominatie, archiefactiedatum, at least the "afgehandeld" and "termijn" brondatum methods). Full selectielijst integration can come later.

4. **DO implement the role system** with at least initiator and behandelaar roles. The betrokkene identification models can be simplified.

5. **LEVERAGE Nextcloud** for what OpenZaak lacks: real-time collaboration, built-in file versioning, user-friendly UI, notification system, and app ecosystem.

6. **Consider ZGW API compatibility layer** -- expose a ZGW-compatible API surface while using OpenRegister internally. This would allow Procest to interoperate with the existing ZGW ecosystem.

---

## Spec Files

| Feature | Spec | Business Logic |
|---------|------|----------------|
| Zaak Lifecycle | [specs/zaak-lifecycle/spec.md](specs/zaak-lifecycle/spec.md) | [business-logic/zaak-lifecycle.md](business-logic/zaak-lifecycle.md) |
| Catalogi / ZaakTypen | [specs/catalogi-zaaktypen/spec.md](specs/catalogi-zaaktypen/spec.md) | -- |
| Documenten API | [specs/documenten-api/spec.md](specs/documenten-api/spec.md) | [business-logic/document-lifecycle.md](business-logic/document-lifecycle.md) |
| Besluiten API | [specs/besluiten-api/spec.md](specs/besluiten-api/spec.md) | [business-logic/besluit-flow.md](business-logic/besluit-flow.md) |
| Autorisaties Model | [specs/autorisaties-model/spec.md](specs/autorisaties-model/spec.md) | [business-logic/authorization-flow.md](business-logic/authorization-flow.md) |
| Rollen & Betrokkenen | [specs/rollen-betrokkenen/spec.md](specs/rollen-betrokkenen/spec.md) | -- |
| Archivering | [specs/archivering/spec.md](specs/archivering/spec.md) | [business-logic/archiving-rules.md](business-logic/archiving-rules.md) |
| Status & Resultaat | [specs/status-resultaat/spec.md](specs/status-resultaat/spec.md) | [business-logic/statustype-progression.md](business-logic/statustype-progression.md) |
| Zaak Relaties | [specs/zaak-relaties/spec.md](specs/zaak-relaties/spec.md) | -- |
| ZaakObjecten | [specs/zaakobjecten/spec.md](specs/zaakobjecten/spec.md) | -- |
| ZaakEigenschappen | [specs/zaakeigenschappen/spec.md](specs/zaakeigenschappen/spec.md) | -- |
