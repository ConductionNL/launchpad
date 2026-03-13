# Open Beheer -- Gemeente Rotterdam Context

## Origin

Open Beheer was originally commissioned by **Gemeente Rotterdam** and developed by
**Maykin B.V.** Rotterdam is the largest municipality by area in the Netherlands and
a pioneer in the Common Ground initiative for modernizing government IT.

## Problem Statement

Dutch municipalities implementing zaakgericht werken (case-based work) on Common Ground
face a practical challenge: the technical registrations (Catalogi API, Zaken API, etc.)
expose developer-oriented admin interfaces that are unusable for functional managers
(functioneel beheerders).

For Rotterdam specifically:
- Functional managers had to navigate multiple disconnected admin interfaces
  (Open Zaak admin, Objecttypen admin, etc.)
- Each interface required separate logins
- Data was presented as raw API fields, not in the context of business processes
- Setting up a zaaktype required configuring statuses, roles, result types, archiving
  rules, document type relations, and object type relations across multiple systems
- This fragmented experience led to errors, inconsistencies, and slow adoption

## Solution

Rotterdam funded Open Beheer as a **unified management interface** that:

1. Provides a single login and a single interface for all registrations
2. Organizes data around business processes rather than technical resources
3. Works entirely through standardized APIs (no custom database access)
4. Makes the Catalogi API accessible to non-technical functional managers

## Governance Evolution

What started as a Rotterdam-funded project has evolved into a multi-stakeholder
effort. The current copyright holders are:

1. **Dimpact** -- cooperative representing 30+ municipalities
2. **Gemeente Den Haag**
3. **Gemeente Rotterdam** (original funder)
4. **Gemeente Utrecht**
5. **Maykin** (developer)

This stakeholder model follows the pattern established by Open Zaak, where multiple
municipalities jointly fund and govern an open-source component.

## Rotterdam's Common Ground Stack

Rotterdam uses a full Common Ground stack including:
- **Open Zaak** -- case management API layer
- **Open Beheer** -- functional management UI (this project)
- **Open Formulieren** -- citizen-facing forms
- **Open Klant** -- customer interaction management
- **Open Notificaties** -- event notifications

Open Beheer fills the "missing middle" in this stack: the interface that lets
Rotterdam's functional managers (not developers) configure how the entire case
management workflow operates.

## Adoption Beyond Rotterdam

With Dimpact (30+ municipalities), Den Haag, and Utrecht as stakeholders, Open Beheer
is positioned for broader adoption across Dutch municipalities. Dimpact's involvement
is particularly significant as they provide shared IT services and can roll out
Open Beheer to their entire member base.
