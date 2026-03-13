# Open Klant — Competitor Analysis

## Overview

- **Website:** https://open-klant.readthedocs.io/
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes
- **Summary:** Registration component for storing and sharing customer data according to the VNG Klantinteracties and Contactgegevens API specifications

## Codebase

- **GitHub:** https://github.com/maykinmedia/open-klant
- **ReadTheDocs:** https://open-klant.readthedocs.io/
- **OpenGem:** https://www.opengem.nl/producten/open-klant/

## Business Model

Open Klant is developed by Maykin Media in collaboration with the municipalities of Amsterdam, Den Haag, Utrecht, and VNG Realisatie. The software is free under EUPL-1.2. Revenue comes through the OpenGem initiative where Maykin provides SaaS hosting and support. Municipalities never pay license fees — they only pay for support and infrastructure costs. Development is co-funded by participating municipalities and governed through VNG Realisatie's standardization process.

## Target Market

Dutch municipalities and government organizations that need a standards-compliant customer interaction registry. Specifically targets organizations implementing the VNG Klantinteracties API standard as part of their Common Ground architecture. Used alongside other components like KISS (contact center), Open Zaak (case management), and Open Inwoner (citizen portal).

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or Dimpact partners

## Key Features

- Full implementation of the VNG Klantinteracties API specification
- Partij (Party) management — stores persons and organizations as customer records
- Contactmoment/KlantenContact registration — logs interactions between citizens and municipal employees
- Digitaal Adres (Digital Address) — manages email, phone, and other contact channels per party
- Interne Taak (Internal Task) — routes follow-up tasks to municipal departments
- Betrokkene (Involved Party) — links parties to specific customer contacts
- Actor management — tracks which employees or systems handled interactions
- Notification support (create/update/delete events for Partij and Interne Taak)
- Admin interface with OIDC authentication
- Token-based API authorization per resource type
- Filtering and search across all resources (by party, contact, task)

## Feature Comparison with Pipelinq

| Feature | Open Klant | Pipelinq |
|---------|-----------|----------|
| Client management (persons) | Yes (Partij - persoon) | Yes |
| Organization management | Yes (Partij - organisatie) | Yes |
| Contact persons (linked) | Partial (Betrokkene, no hierarchy) | Yes |
| Lead pipeline (kanban) | No | Yes |
| Request intake | Partial (Interne Taak routing) | Yes |
| Contact moments logging | Yes (core feature) | Yes |
| My Work queue | No (API only, no UI) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | No | Yes |
| Case management integration | Yes (via ZGW APIs) | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes (token-based per resource) | Yes |
| Audit trail | Partial (contact log) | Yes |

## Strengths

- Reference implementation of the VNG Klantinteracties API standard — the officially recognized way to store customer interactions in Dutch government
- Strong ecosystem integration with KISS, Open Zaak, and Open Inwoner, forming a complete Common Ground citizen interaction stack
- Backed by VNG Realisatie standardization process, giving it regulatory weight and broad municipal adoption

## Weaknesses

- API-only component with no user-facing interface — requires a separate front-end like KISS to be useful for end users
- Not a CRM — no pipeline management, lead tracking, or sales/relationship management capabilities
- Limited to the narrow VNG Klantinteracties scope — cannot manage organizations with hierarchies, linked contacts, or any custom relationship structures

## Notes

Open Klant is the official VNG reference implementation for customer interaction data storage. It competes with Pipelinq primarily on the contact moments and client management features. However, Open Klant is a headless API component that stores structured customer interaction data, while Pipelinq is a full CRM with a user interface, pipeline management, and Nextcloud integration. For municipalities, the competitive dynamic is interesting: Open Klant provides the standardized API backend, but Pipelinq could implement the same Klantinteracties API specification while offering a far richer user experience. Pipelinq could also integrate with Open Klant as a data source rather than competing directly.
