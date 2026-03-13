# KISS — Competitor Analysis

## Overview

- **Website:** https://github.com/Klantinteractie-Servicesysteem
- **Open Source:** Yes (EUPL)
- **Self-Hosted:** Yes
- **Summary:** Open-source klantinteractie system for Dutch municipalities

## Codebase

- **Organization:** https://github.com/Klantinteractie-Servicesysteem
- **Frontend:** https://github.com/Klantinteractie-Servicesysteem/KISS-frontend
- **Documentation:** https://klantinteractie-servicesysteem.readthedocs.io/

## Business Model

Government-funded collaborative development. KISS is developed by ICATT, commissioned by the Municipality of Utrecht and Dimpact (a cooperative of 40+ Dutch municipalities). No commercial licensing — the software is free under the EUPL license. Costs for municipalities are limited to implementation, hosting, and support services provided by Dimpact and its partners. The development is funded through municipal contributions to Dimpact.

## Target Market

Exclusively Dutch municipalities and government organizations. Designed to support Klantcontact Medewerkers (KCM — customer contact employees) who handle citizen and business inquiries. Part of the Common Ground ecosystem for Dutch government IT.

## Pricing

- **Software:** Free (EUPL license)
- **Implementation:** Through Dimpact membership and partner services
- **Hosting:** Municipalities arrange through their own infrastructure or Dimpact partners
- No per-user or per-seat licensing

## Key Features

- Unified search across municipal knowledge bases and registries (via Elasticsearch)
- Contact moment registration (contactmomenten) following the VNG Klantinteractie API standard
- Citizen profile view with integrated data from BRP (Basisregistratie Personen)
- Integration with Open Klant for customer/contact registration
- Integration with Open Zaak for case management (ZGW APIs)
- Integration with KvK API for business data
- News and work instructions management (OpenPub standard)
- Management information API for reporting
- Common Ground compliant architecture
- OIDC authentication (Azure AD compatible)
- Runs in Kubernetes cluster
- Built with Vue (frontend) and .NET + PostgreSQL (backend)

## Feature Comparison with Pipelinq

| Feature | KISS | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Partial (read-only from BRP) | Yes |
| Organization management | Partial (read-only from KvK) | Yes |
| Contact persons (linked) | No | Yes |
| Lead pipeline (kanban) | No | Yes |
| Request intake | Yes (contact moment registration) | Yes |
| Contact moments logging | Yes (core feature) | Yes |
| My Work queue | Yes (KCM work queue) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | No | Yes |
| Case management integration | Yes (Open Zaak/ZGW) | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Partial (OIDC-based) | Yes |
| Audit trail | Yes (contactmomenten log) | Yes |

## Strengths

- Purpose-built for Dutch municipalities — fully Common Ground compliant with ZGW API integration
- Integrated with national registries (BRP, KvK) and municipal systems (Open Klant, Open Zaak)
- Funded and governed by municipalities via Dimpact — aligned with government procurement and compliance requirements

## Weaknesses

- Not a CRM — focused exclusively on customer contact support, no pipeline, no lead management, no organization management
- Limited to Dutch municipal context — cannot be used for general-purpose CRM or outside government
- Dependent on the Common Ground ecosystem (Open Klant, Open Zaak) — requires full stack to be useful

## Notes

KISS is the most relevant competitor for Pipelinq in the Dutch government space, but it is not a CRM. It is a Klantinteractie Servicesysteem — a tool for municipal contact center employees to handle citizen inquiries. It overlaps with Pipelinq primarily in contact moment logging and case management integration. KISS integrates with the same Common Ground ecosystem (Open Klant, Open Zaak) that Pipelinq targets. The key differentiation is that Pipelinq provides full CRM capabilities (pipeline management, organization management, contact management) within the Nextcloud ecosystem, while KISS is a narrowly focused contact center tool. For municipalities, KISS and Pipelinq could be complementary rather than competing.
