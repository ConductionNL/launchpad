# Open Formulieren (Open Forms) — Competitor Analysis

## Overview

- **Website:** https://open-forms.readthedocs.io/
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes
- **Summary:** Smart and dynamic e-forms platform for Dutch government organizations, enabling no-code form creation with built-in authentication, prefill, payment, and case registration capabilities

## Codebase

- **GitHub:** https://github.com/open-formulieren/open-forms
- **SDK:** https://github.com/open-formulieren/open-forms-sdk
- **Organization:** https://github.com/open-formulieren
- **ReadTheDocs:** https://open-forms.readthedocs.io/

## Business Model

Open Formulieren was initiated by Maykin Media around mid 2020 and later donated to Dimpact after winning a tender. It is developed collaboratively by Maykin Media, commissioned by Dimpact and municipalities including Den Haag and Utrecht. The software is free under the EUPL license with no license fees. Revenue is generated through the OpenGem initiative where Maykin provides SaaS hosting, implementation, and support services. Municipalities only pay for support and the hardware usage where the components run, not for the software itself.

## Target Market

Dutch municipalities and government organizations that need citizen-facing e-forms for service requests, permits, and other intake processes. Used by 150+ municipalities via Dimpact. Focused entirely on the Dutch government Common Ground ecosystem.

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or Dimpact partners
- **Implementation:** Through Dimpact membership or direct engagement with Maykin Media

## Key Features

- Drag-and-drop form builder with no-code logic rules and conditional field visibility
- Authentication via DigiD, eHerkenning, eIDAS, SAML, and OpenID Connect
- Automatic prefill from national registries (BRP, KvK/Handelsregister, Haal Centraal)
- Payment integration with online payment providers (e.g., Ogone)
- Submission backends: ZGW APIs (Open Zaak), StUF-ZDS, Objects API, email, or local storage
- Plugin-based architecture for extensibility (auth, prefill, payments, registrations)
- Map component with geo-location support
- Multi-step forms with progress tracking and save-and-resume
- WCAG accessibility compliant
- NL Design System theming support
- Appointment scheduling integration (via QMatic, JCC)
- Export and reporting capabilities for form submissions

## Feature Comparison with Procest

| Feature | Open Formulieren | Procest |
|---------|-----------------|---------|
| Case lifecycle management | No (form intake only) | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | Yes (submission registration) | Yes |
| Deadline tracking | No | Yes |
| Task assignment | No | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | No | Yes |
| Sub-cases | No | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Partial (submission log) | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes (admin-level) | Yes |
| WCAG AA accessible | Yes | Yes |

## Strengths

- Excellent form creation experience with no-code logic, prefill, and authentication — the best open-source e-forms solution in the Dutch government space
- Deep integration with national registries (BRP, KvK) and Common Ground APIs, reducing manual data entry for citizens
- Very large adoption base (150+ municipalities via Dimpact) providing stability and continuous development funding

## Weaknesses

- Forms only — no case handling, task management, or workflow capabilities after the form is submitted
- Completely dependent on external systems (Open Zaak, Objects API) for post-submission processing — cannot manage the case lifecycle
- No Nextcloud integration — operates as a standalone web application requiring its own infrastructure and authentication

## Technical Stack

- **Backend:** Python 3.12, Django framework (86.1% Python codebase)
- **Frontend:** JavaScript SDK built on form.io (`@open-formulieren/formiojs`), published as NPM package (`@open-formulieren/sdk`)
- **Database:** PostgreSQL
- **Task queue:** Celery with automatic retry (registration, emails, cleanup)
- **Deployment:** Docker (`openformulieren/open-forms`), Docker Compose
- **API:** REST (OpenAPI 3), divided into public (versioned) and private endpoints
- **Current version:** 3.5.0-alpha.1 (350+ releases, 14,000+ commits)

## Architecture

The architecture is fully plugin-based across four categories:
1. **Authentication plugins** — DigiD, eHerkenning, eIDAS, SAML, OIDC
2. **Prefill plugins** — Haal Centraal BRP v2.0, StUF-BG, KvK/Handelsregister, Objects API (v3.0+)
3. **Registration plugins** — ZGW APIs, StUF-ZDS, Objects API, Email, MS Graph
4. **Payment plugins** — Ogone/Ingenico (legacy), Worldline (v3.3+)

**Processing flow:**
```
[Citizen Browser] → [SDK (JS in CMS)] → [REST API] → [Django Backend]
                                                            ↓
                                                    [Celery Queue]
                                                            ↓
                                      [Registration Plugin (ZGW/Objects/StUF/Email)]
                                                            ↓
                                          [External System (OpenZaak, etc.)]
```

## Municipalities & Adoption

- **150+ municipalities** via Dimpact membership
- **30+ municipalities** with components in production
- **Known adopters:** Den Haag, Utrecht, Rotterdam, Horst aan de Maas, Venlo, Roermond, Nijmegen, Provincie Zeeland, SED municipalities
- **Development consortium:** Dimpact + Den Haag + Utrecht + SED + Maykin Media
- **Distribution:** OpenGem SaaS, Dimpact procurement, self-hosted Docker

## VNG Standards Compliance

- **ZGW APIs** — Full compliance for outbound case registration
- **WMEBV** — E-forms accessibility law compliance
- **SDG** — Single Digital Gateway (EU) with SDG Annex II form designs
- **WCAG AA** — Accessibility compliance
- **NL Design System** — Government design tokens
- **StUF-BG/ZDS** — Legacy SOAP integration (migration path to REST)
- **Haal Centraal** — Modern REST APIs to BRP and KvK

## Recent Version Highlights

- **v3.0:** Objects API prefill from existing records; update existing objects during registration; internal cleanups
- **v3.1:** Map component with lines and polygons (beyond point markers); appointment form flow improvements
- **v3.2:** Nijmegen contributing municipality; per-component document type for uploads
- **v3.3:** Worldline payment provider (Ogone Legacy replacement); migration tooling

## Detailed Documentation

See `/docs/` directory for in-depth analysis:
- `architecture.md` — Component overview, plugin system, infrastructure comparison
- `form-builder.md` — Form designer capabilities, component library, logic engine
- `submission-flow.md` — End-to-end submission lifecycle
- `registration-backends.md` — ZGW, Objects API, StUF-ZDS, Email backends
- `authentication.md` — DigiD, eHerkenning, eIDAS, OIDC, SAML
- `prefill.md` — BRP, KvK, Objects API prefill sources
- `payment.md` — Ogone/Worldline payment integration
- `ecosystem.md` — Municipalities, Common Ground ecosystem, distribution channels
- `api-documentation.md` — API structure, OpenAPI spec, SDK communication
- `vng-standards.md` — VNG standards and regulatory compliance
- `pdf-links.md` — All documentation URLs and PDF downloads

## Competitive Specs

See `/specs/` directory for feature-by-feature comparison with Procest:
- `form-building.md` — Form designer gap analysis
- `submission-and-registration.md` — Submission flow and registration comparison
- `zgw-objects-api-integration.md` — ZGW and Objects API integration depth
- `authentication-and-prefill.md` — Authentication and prefill relevance
- `payment-integration.md` — Payment feature assessment
- `competitive-positioning.md` — Strategic positioning and recommendations

## Notes

Open Formulieren is not a direct competitor to Procest but rather a complementary intake mechanism. Forms built in Open Formulieren typically submit data to Open Zaak or the Objects API, which then needs a case handling application like Procest to manage the resulting work. The competitive overlap is narrow: Procest could provide its own form-based intake that feeds directly into its case management workflow, eliminating the need for a separate forms platform. For municipalities already using Open Formulieren, Procest would position itself as the case handling layer that receives form submissions via ZGW APIs.

## Strategic Recommendation

**Complementary integration, not competition.** Open Formulieren dominates citizen-facing intake (150+ municipalities, DigiD/eHerkenning, prefill, payment). Procest dominates internal case management (lifecycle, tasks, deadlines, documents, decisions). The integration point is ZGW APIs — Open Formulieren creates Zaken, Procest manages them. Procest should ensure seamless ZGW intake from Open Formulieren and position itself as "what happens after the form is submitted."
