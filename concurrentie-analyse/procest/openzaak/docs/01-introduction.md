# OpenZaak Introduction

## What is OpenZaak?

Open Zaak is a modern, open-source data- and services-layer designed to support **zaakgericht werken** (case-oriented working), a Dutch approach to case management. It functions as the hub within an organization for cases (zaken) and documents.

**Current version:** 1.27.0 (released 2026-02-06)
**License:** EUPL-1.2
**Language:** Python 3.12 (Django)
**Source:** https://github.com/open-zaak/open-zaak
**Documentation:** https://open-zaak.readthedocs.io/en/latest/
**Docker Hub:** https://hub.docker.com/u/openzaak

## What is Zaakgericht Werken?

Zaakgericht werken is a form of process-oriented working used by Dutch municipalities and increasingly by national government agencies to handle requests from citizens and businesses. The "zaak" (case) is central. A zaak is a coherent body of work with a defined trigger and a defined result, where quality and turnaround time must be monitored. The ZGW APIs support the registration of all metadata and data involved in zaakgericht werken.

## Available APIs

Open Zaak exposes the following APIs:

| API | Spec Version | Purpose |
|-----|-------------|---------|
| Zaken API | 1.5.1 | Case instances — lifecycle, status, roles, properties |
| Documenten API | 1.4.2 | Documents and information objects |
| Catalogi API | 1.3.1 | Case type catalogs — zaaktypen, statustypen, resultaattypen |
| Besluiten API | 1.1.0 | Decisions taken in context of cases |
| Autorisaties API | 1.0.0 | Application authorization management |

The **Notificaties API** is required but available separately through **Open Notificaties**.

## VNG Standards

These APIs follow the standard defined by VNG Realisatie "API's voor Zaakgericht Werken", developed under the Common Ground initiative. The standards include:

- **Content APIs:** Catalogi, Zaken, Documenten, Besluiten
- **Generic APIs:** Notificaties, Autorisaties

## Foundation

Open Zaak is based on the reference implementation of the "API's voor Zaakgericht werken" made by VNG Realisatie. It delivers production-ready software built for municipal use.

## Who Built It

Developed by **Maykin Media B.V.** on behalf of:
- Amsterdam, Rotterdam, Utrecht, Tilburg, Arnhem, Haarlem, 's-Hertogenbosch, Delft
- Coalition of Hoorn, Medemblik, Stede Broec, Drechterland, Enkhuizen (SED)
- Under coordination of **Dimpact**

## Key Differentiator from Procest

Open Zaak is an **API-only backend layer** with no end-user interface. It provides the data storage and API compliance layer. Case handling applications like Procest, Dimpact ZAC, or Valtimo connect to Open Zaak as consumers. Procest provides the full case-handling experience (UI, workflows, tasks, deadlines) natively in Nextcloud, while Open Zaak focuses purely on ZGW API compliance as the data layer.
