# Open Product — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (20 specs, 7 BL diagrams), Documentation (6 docs, 6 specs), Browser (screenshots + comparison)
**Verdict**: Purpose-built product catalog — strong on UPL/SDG, weak on flexibility

---

## Executive Summary

Open Product is Maykin Media's product type/instance registry for government product catalogs (PDC). It's young (first release April 2025, 3 GitHub stars) and purpose-built for one use case: managing municipal product/service catalogs with UPL and SDG compliance.

**Not a direct threat to OpenRegister** — OpenRegister is generic (any data), Open Product is fixed (only products). But it shows what government-specific features a product catalog needs.

## Key Comparison

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Data model | Fixed product schema (~15 entities) | Generic JSON Schema (any data) |
| UPL compliance | Built-in with CSV import | Not available |
| SDG compliance | Built-in doelgroep/multilingual | Not available |
| Pricing | Date-scheduled + DMN rules | Not available |
| Product lifecycle | 7-state machine with auto-transitions | No lifecycle states |
| Multilingual | Per-field NL/EN translation | Not available |
| Search | None (basic ORM filters only) | Full-text + faceted + semantic |
| UI | Django Admin only | Vue.js + NL Design |
| Flexibility | Products only | Any schema |
| Multi-tenancy | No | Yes (registers) |
| File handling | No | Nextcloud native |
| AI/MCP | No | Yes |

## Features Worth Adopting in OpenRegister

| Feature | Priority | Complexity |
|---------|----------|------------|
| **Multilingual field support** | HIGH | Medium — needs per-field translation storage |
| **Schema lifecycle states** | MEDIUM | Low — add status field to schemas |
| **Date-driven automation** | MEDIUM | Low — Celery/cron for status transitions |
| **UPL entity + import** | LOW | Low — import CSV into a register |
| **DMN integration** | LOW | Medium — external DMN engine calls |
| **Per-type permissions** | MEDIUM | Already partially implemented |

## Specs Created

### From Codebase (20 specs)
product-type-management, product-instance-lifecycle, upl-compliance, sdg-doelgroep-compliance, thema-categorization, pricing-engine, dmn-integration, content-management, location-organization-contact, urn-mapping, json-schema-validation, permission-system, audit-logging, notifications, versioning, i18n-translations, api-filtering, data-export, celery-automation, external-code-parameters

### From Documentation (6 specs)
producttype-management, product-instance-management, standards-compliance, pricing-dmn, auth-permissions, multilingual-content

### From Browser (1 comparison spec + screenshots)
comparison-with-openregister — strategic comparison with gap analysis

### Business Logic Diagrams (7)
product-lifecycle-state-machine, producttype-create-flow, product-create-flow, upl-enforcement-flow, urn-url-resolution-flow, pricing-resolution-flow, permission-enforcement-flow
