# Open VTB — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (every file), Documentation (GitHub + API specs), Browser walkthrough (15 screenshots)
**Verdict**: Pre-release headless API register — complementary to Pipelinq, not a direct competitor

---

## Executive Summary

Open VTB (Verzoeken, Taken en Berichten) is Maykin Media's v0.1.0 headless API for requests, tasks, and messages. It's **very early-stage** (2 GitHub stars, 0 forks, no known production deployments). The VNG Verzoeken API standard it was based on was **archived in 2023**.

However, it has well-designed patterns worth adopting:

| Open VTB Concept | Pipelinq Equivalent |
|---|---|
| Verzoek (request) | Lead / Request intake |
| VerzoekType (versioned) | — (no versioned request types) |
| ExterneTaak | My Work queue item |
| Betaaltaak | — (no payment tasks) |
| Formuliertaak | — (no FormIO forms) |
| Bericht | — (no structured messages) |
| URN addressing | — (no cross-system identifiers) |

## Maturity Assessment

- **Version**: 0.1.0 (pre-release)
- **GitHub**: 2 stars, 0 forks
- **Standards**: VNG Verzoeken API archived 2023, no standard for Taken/Berichten
- **Production use**: None known
- **ReadTheDocs**: Not publicly accessible
- **Docker Hub**: Image returns 404
- **Last activity**: Migrations dated March 2026, but development may have stalled

## Features Worth Adopting in Pipelinq

| Feature | What It Does | Priority |
|---------|-------------|----------|
| **Payment tracking** | VerzoekBetaling + Betaaltaak with IBAN validation, amounts, status | MEDIUM |
| **Versioned request types** | Draft → Published → Deprecated with JSON Schema validation | MEDIUM |
| **FormIO form definitions** | Embed form definitions in task types | LOW |
| **Auto deadline/reminder calc** | Automatic reminder date from deadline | HIGH (simple, valuable) |
| **URN cross-system addressing** | RFC 8141 URNs for linking to external systems | LOW |
| **Immutable message trail** | Create-only messages with scheduled publication | MEDIUM |
| **Read tracking** | `gelezen` flag on messages | LOW |

## Features Pipelinq Already Has

- Full Vue.js UI (Open VTB has none)
- Pipeline/kanban views
- Nextcloud integration (files, users, sharing)
- Contact management
- Search and filtering
- Import/export
- n8n workflow automation
- NL Design theming

## Specs Created

### From Codebase (9 specs)
verzoeken, taken, berichten, betaaltaak, formuliertaak, gegevensuitvraagtaak, verzoektype-versioning, urn-addressing, oidc-auth

### From Documentation (9 specs + 7 docs)
Same specs enriched, plus: API specs for all 3 APIs (45 endpoints total), ecosystem analysis, VNG standards context

### From Browser (2 specs + 15 screenshots)
open-vtb-analysis (complete technical), feature-comparison

### Business Logic Diagrams (4)
verzoeken-flow, taken-flow, berichten-flow, system-architecture

## Conclusion

Open VTB is more of a **pattern library** than a competitor. It validates that Pipelinq's approach (full app with UI) is the right one — the headless API-only approach requires separate frontend development. The useful patterns to borrow are auto-reminders, payment tracking, and versioned type definitions.
