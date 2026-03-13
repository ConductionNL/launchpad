# Open Beheer -- Ecosystem Integrations

## Position in the Common Ground Ecosystem

Open Beheer is a **management UI layer** that sits on top of Common Ground
registration components. It does not replace any API or data store; it provides a
human-friendly interface for functional managers to configure the registrations that
power zaakgericht werken (case-based work).

```
+-----------------------------------------------------+
|                 Open Beheer (UI + BFF)               |
+-----+-------------+------------------+--------------+
      |             |                  |
      v             v                  v
+----------+  +-------------+  +----------------+
| Open Zaak|  | Selectie-   |  | Objecttypen    |
| Catalogi |  | lijst API   |  | API            |
| API      |  | (VNG)       |  | (Maykin)       |
+----------+  +-------------+  +----------------+
```

## Direct Integrations

### 1. Open Zaak -- Catalogi API (Primary)

Open Beheer's primary integration. It connects to the Catalogi API endpoint of an
Open Zaak instance (or any ZGW-compliant Catalogi API provider).

**What it manages:**
- Catalogi (catalog containers)
- Zaaktypen (case types) -- full CRUD
- Statustypen (status types) -- nested under zaaktype
- Resultaattypen (result types) -- nested under zaaktype
- Roltypen (role types) -- nested under zaaktype
- Besluittypen (decision types) -- nested under zaaktype
- Eigenschappen (properties) -- nested under zaaktype
- InformatieObjectTypen (document types) -- full CRUD
- ZaakTypeInformatieObjectTypen (case-document relations) -- nested
- ZaakObjectTypen (case-object type relations) -- nested

**Connection:** Configured via `zgw-consumers` Service model. Each service has a
slug, API root URL, client credentials, and API type (`ztc`).

**API version:** Catalogi API 1.x (ZGW standard)

### 2. Selectielijst API (VNG)

National selection list maintained by VNG Realisatie that defines archiving rules for
Dutch municipalities.

**What it provides:**
- Procestypen (process types) used in resultaattype configuration
- Retention periods and archiving actions
- National reference data for compliance

**Connection:** Configured via `APIConfig.selectielijst_service_identifier`.

### 3. Objecttypen API

Object type definitions used for zaakobjecttypen -- linking case types to object
types.

**What it provides:**
- Object type definitions from a central registry
- Used when configuring which object types can be associated with a case type

**Connection:** Configured via `APIConfig.objecttypen_service_identifier`.

## Indirect / Ecosystem Relationships

### Open Klant

Open Beheer does **not** directly integrate with Open Klant (customer interaction
management). However, the zaaktypen configured in Open Beheer are consumed by
Open Klant when handling customer interactions tied to specific case types.

### Open Producten (Open Product)

No direct integration. Open Producten manages product types and products. The
relationship is indirect: both consume/produce data in the Common Ground ecosystem.
A future integration could link product types to zaaktypen.

### Open Zaak -- Zaken API / Documenten API

Open Beheer only connects to the **Catalogi** component of Open Zaak (type
definitions). It does not interact with the Zaken API (case instances) or the
Documenten API (documents). It is purely a type/schema management tool.

### Open Formulieren (Open Forms)

Open Formulieren uses zaaktypen and informatieobjecttypen configured through
Open Beheer to automatically create cases and attach documents when forms are
submitted.

### Open Archiefbeheer

Open Archiefbeheer (Maykin's archive management tool) uses the archiving
configuration (resultaattypen, selectielijst references) that functional managers
set up through Open Beheer.

## Relationship to Open Zaaktypebeheer

Open Zaaktypebeheer (`maykinmedia/open-zaaktypebeheer`) is an older, narrower tool
from Maykin that only manages the relation between informatieobjecttypen and
zaaktypen. Open Beheer is its broader successor -- it covers the full Catalogi API
surface (zaaktypen, statustypen, resultaattypen, roltypen, besluittypen,
eigenschappen, informatieobjecttypen, and all their relations).

Both projects are currently active on GitHub. Open Zaaktypebeheer is at v0.1.3 and
appears to be in maintenance mode, while Open Beheer (v0.9.0) is under active
development with a much larger scope.

## Stakeholders and Governance

Copyright is jointly held by five stakeholders:

1. **Dimpact** (https://www.dimpact.nl) -- cooperative of 30+ municipalities
2. **Gemeente Den Haag** (https://www.denhaag.nl)
3. **Gemeente Rotterdam** (https://www.rotterdam.nl) -- original commissioning party
4. **Gemeente Utrecht** (https://www.utrecht.nl)
5. **Maykin** (https://www.maykinmedia.nl) -- developer

Code contributions transfer copyright to the stakeholder group.
