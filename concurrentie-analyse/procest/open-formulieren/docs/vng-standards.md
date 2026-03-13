# Open Formulieren — VNG Standards & Compliance

## Overview

Open Formulieren aligns with multiple VNG (Vereniging van Nederlandse Gemeenten) standards and Dutch government regulations for digital forms and citizen services.

## Standards Compliance

### ZGW APIs (Zaakgericht Werken)

- **Standard:** VNG Realisatie ZGW API specifications
- **Components:** Zaken API, Documenten API, Catalogi API, Besluiten API
- **Usage:** Registration backend for form submissions → creates Zaken and Informatieobjecten
- **Compliance level:** Full compliance with ZGW API v1.x specifications
- **Source:** https://standaarden.vng.nl/

### WMEBV (Wet Modernisering Elektronisch Bestuurlijk Verkeer)

- **Regulation:** Dutch law mandating accessible electronic communication with government
- **Requirements:** E-forms must be accessible, usable, and available for all government services
- **Open Formulieren compliance:** Designed specifically to help municipalities comply with WMEBV
- **VNG guidance:** "WMEBV Hulpgids: e-formulieren en notificeren" (July 2024)
- **Source:** https://vng.nl/sites/default/files/2024-07/wmebv_hulpgids_e-formulieren_en_notificeren_juli_2024.pdf

### SDG (Single Digital Gateway)

- **Regulation:** EU Regulation 2018/1724 — single access point for EU citizens
- **Requirements:** Cross-border access to government services via e-forms
- **Open Formulieren compliance:**
  - Generic SDG Annex II form designs developed on behalf of VNG
  - eIDAS authentication support for cross-border identification
  - Multi-language support capabilities
- **Source:** https://openwebconcept.nl/thema/formulieren-openpdd

### WCAG AA (Web Content Accessibility Guidelines)

- **Standard:** W3C WCAG 2.1 Level AA
- **Requirements:** Forms must be accessible to people with disabilities
- **Open Formulieren compliance:** Built with NL Design System interaction patterns for accessibility

### NL Design System

- **Standard:** Dutch government design system for consistent UI/UX
- **Usage:** CSS custom properties (design tokens) for theming forms to match municipality branding
- **Compliance:** Forms use NL Design System interaction patterns
- **Source:** https://nldesignsystem.nl/

### StUF (Standaard Uitwisseling Formaat)

- **Standard:** VNG standard for SOAP/XML-based data exchange
- **StUF-BG:** Basisgegevens — access to BRP (population register) data
- **StUF-ZDS:** Zaak- en Documentservices — case registration in legacy systems
- **Status:** Legacy — being replaced by REST-based APIs (Haal Centraal, ZGW APIs)

### Haal Centraal

- **Standard:** VNG Realisatie initiative for modern REST APIs to national registries
- **BRP Personen Bevragen:** Access to citizen data (v2.0)
- **Handelsregister:** Access to Chamber of Commerce data
- **Usage:** Prefill plugins for automatic form field population

## Comparison with Procest Standards Compliance

| Standard | Open Formulieren | Procest |
|----------|-----------------|---------|
| ZGW APIs | Yes (outbound registration) | Yes (bidirectional case management) |
| WMEBV | Yes (form accessibility) | N/A (internal tool) |
| SDG | Yes (cross-border forms) | No |
| WCAG AA | Yes | Yes |
| NL Design System | Yes | Yes |
| StUF-BG | Yes (prefill) | No |
| StUF-ZDS | Yes (registration) | No |
| Haal Centraal BRP | Yes (prefill) | No |
| Haal Centraal HR | Yes (prefill) | No |
| CMMN 1.1 | No | Yes |

### Analysis

Open Formulieren's standards compliance is focused on the **intake/citizen-facing** side of government services (WMEBV, SDG, accessibility, prefill from national registries). Procest's standards compliance is focused on the **internal/case-management** side (ZGW APIs for case lifecycle, CMMN for process modeling). They address different parts of the same standards landscape.

For municipalities, full standards compliance requires both: Open Formulieren for compliant citizen-facing intake, and a case management system like Procest for compliant case handling.
