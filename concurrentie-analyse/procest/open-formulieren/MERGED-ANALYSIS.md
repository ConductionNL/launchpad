# Open Formulieren — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (800+ files, 25 Django apps, 40+ plugins), Documentation (11 docs), Browser walkthrough (33 screenshots)
**Verdict**: Complementary, not competitive — citizen intake forms, not case management

---

## Executive Summary

Open Formulieren is Maykin Media's no-code smart e-forms platform for government. It handles the citizen-facing intake side: form building, DigiD/eHerkenning authentication, BRP/KvK prefill, payment processing, and registration to ZGW APIs or Objects API. It does NOT handle case processing.

**Relationship to Procest**: Open Forms creates cases, Procest manages them. The ZGW APIs are the integration seam.

```
Citizen → Open Forms (intake) → ZGW API / Objects API → Procest (case handling) → Decision
```

## Scale

- 800+ source files, 25 Django apps
- 40+ plugins (8 auth, 7 registration, 11 prefill, 8 analytics, 3 appointment, 2 payment)
- 150+ municipalities via Dimpact
- FormIO-based form builder with 15+ field types

## Key Capabilities

| Category | Features |
|----------|----------|
| **Form Builder** | No-code drag-and-drop, 15+ field types, multi-step wizard, reusable definitions, JSON Logic rules, DMN decision tables |
| **Auth** | DigiD, eHerkenning, DigiD Machtigen, eIDAS, OIDC, Yivi, co-signing |
| **Prefill** | Haal Centraal BRP v2, KvK, StUF-BG, Suwinet, Objects API |
| **Registration** | ZGW APIs, Objects API, StUF-ZDS, Email, Camunda, SharePoint, JSON |
| **Payment** | Ogone/Worldline, iDEAL, price calculation per product |
| **Other** | Appointment scheduling, NL Design theming, GDPR data removal, analytics |

## Relevance to Procest

### NOT competing on
- Form building (Open Forms dominates with 150+ municipalities)
- DigiD/eHerkenning citizen auth
- Prefill from government registries
- Payment processing

### Integration opportunity
- Open Forms creates zaken via ZGW API → Procest picks them up
- Open Forms registers to Objects API → OpenRegister processes
- Procest could expose an intake API that Open Forms registers to

### Features worth learning from
| Feature | What It Does | Procest Relevance |
|---------|-------------|-------------------|
| **Submission lifecycle** | 10-stage state machine with retry | Pattern for case lifecycle |
| **Co-signing workflow** | V2 out-of-band co-sign | Pattern for multi-party approval |
| **Validation plugins** | BSN 11-proof, IBAN, KvK | Useful for client data validation |
| **Data removal** | GDPR retention + anonymization | Archiving compliance |
| **Multi-domain** | Single instance, per-domain branding | Multi-tenant pattern |

## Specs Created

### From Codebase (20 specs)
form-engine, submission-lifecycle, authentication, registration-backends, prefill-plugins, payment-processing, appointments, form-logic, cosigning, theming-nl-design, dmn-decision-tables, form-builder-admin, email-notifications, file-uploads, multi-step-wizard, validation-plugins, analytics, multidomain, data-removal, form-variables

### From Documentation (6 specs)
form-building, submission-and-registration, zgw-objects-api-integration, authentication-and-prefill, payment-integration, competitive-positioning

### From Browser (33 screenshots + live walkthrough doc)
Full admin coverage: login, MFA, dashboard, form builder (all 11 tabs), field editor (7 sub-tabs), registration backends, themes, submissions, configuration, appointments, API docs, public form SDK

### Business Logic Diagrams (5)
submission-lifecycle, registration-backend-flow, payment-flow, prefill-flow, digid-auth-flow

## Strategic Recommendation

**Integrate, don't compete.** Open Forms owns citizen intake with dominant market share. Procest should:
1. Accept cases from Open Forms via ZGW API / Objects API
2. Position as "what happens after the form is submitted"
3. Borrow patterns: submission lifecycle, co-signing, validation plugins, data removal
