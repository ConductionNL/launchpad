# Open VTB vs Pipelinq -- Feature-by-Feature Comparison

## Summary

Open VTB is a **headless API register** for Verzoeken (requests), Taken (tasks), and Berichten (messages). It has no user-facing frontend -- only a Django admin panel and REST APIs. Pipelinq is a **full Nextcloud application** with a visual frontend for managing pipelines, cases, and client interactions. They occupy different layers in the government software stack.

---

## Feature Matrix

### Request/Case Management

| Capability | Open VTB | Pipelinq | Winner |
|-----------|----------|----------|--------|
| Request intake (typed forms) | VerzoekType with JSON Schema per version, validated submission | Pipeline steps with configurable fields | Open VTB (stronger typing) |
| Request versioning | VerzoekTypeVersion with draft/published/deprecated lifecycle | No versioning concept | Open VTB |
| Payment tracking | VerzoekBetaling inline (amount, currency, provider, status) | Not built-in | Open VTB |
| Geospatial data | PostGIS geometry per request | Not supported | Open VTB |
| Source tracking | VerzoekBron (source app + identifier) | Not built-in | Open VTB |
| Attachment management | Bijlagen via URN references to documents | File uploads via Nextcloud | Pipelinq (actual files) |
| Visual workflow | Not supported | Pipeline builder with steps, conditions, views | Pipelinq |
| Case dashboard | Admin list only | Rich Vue.js views with faceted search | Pipelinq |

### Task Management

| Capability | Open VTB | Pipelinq | Winner |
|-----------|----------|----------|--------|
| Task types | 3 polymorphic types: Betaal, GegevensUitvraag, Formulier | Single generic task type | Open VTB (richer model) |
| Task status workflow | open -> uitgevoerd / niet_uitgevoerd / afgebroken / verwerkt | Custom per pipeline | Pipelinq (more flexible) |
| Auto-reminders | datum_herinnering auto-calculated N days before deadline | Not built-in | Open VTB |
| Embedded form tasks | FormulierTaak with FormIO-compatible JSON definitions | Not supported | Open VTB |
| Payment tasks | BetaalTaak with IBAN, amount, currency validation | Not supported | Open VTB |
| External form tasks | GegevensUitvraagTaak with pre-fill and response capture | Not supported | Open VTB |
| Task assignment | URN-based (is_toegewezen_aan, wordt_behandeld_door) | Nextcloud user-based | Pipelinq (integrated users) |
| Task UI | Admin panel only | Full Nextcloud UI with lists, filters | Pipelinq |

### Messaging

| Capability | Open VTB | Pipelinq | Winner |
|-----------|----------|----------|--------|
| Structured messages | Bericht with subject, body, recipient, dates, attachments | No dedicated messaging | Open VTB |
| MijnOverheid integration | Forward via bericht_type to national message box | Not supported | Open VTB |
| Message read tracking | geopend_op timestamp | Not applicable | Open VTB |
| Scheduled publishing | publicatiedatum for delayed visibility | Not applicable | Open VTB |
| Markdown support | Basic Markdown for local portals, newlines for MijnOverheid | Not applicable | Open VTB |
| Audit trail | Create + read only (no update/delete via API) | Not applicable | Open VTB |

### Platform & Integration

| Capability | Open VTB | Pipelinq | Winner |
|-----------|----------|----------|--------|
| User interface | None (admin only) | Full Nextcloud Vue.js app | Pipelinq |
| API design | VNG-compliant, 3 separate OAS specs | Nextcloud REST API | Open VTB (standards) |
| Authentication | Token + OIDC + 2FA | Nextcloud SSO | Tie |
| User management | Django users + OIDC | Nextcloud users/groups | Pipelinq (richer) |
| File management | URN references only | Full Nextcloud file system | Pipelinq |
| NL Design System | Not applicable (no UI) | Full theming support | Pipelinq |
| Search/filtering | Basic admin filters | Faceted search with configurable facets | Pipelinq |
| Notifications | Webhook subscriptions (planned) | Nextcloud notifications | Pipelinq |
| Multi-tenancy | Single instance per deployment | Nextcloud multi-user | Pipelinq |
| Observability | OpenTelemetry built-in | Standard Nextcloud logging | Open VTB |

---

## Integration Opportunities

Open VTB and Pipelinq could work together:

1. **Pipelinq as VTB consumer** -- Pipelinq pipeline steps could create Verzoeken, assign Taken, and send Berichten via Open VTB APIs
2. **VTB as backend register** -- Open VTB provides the standardized data store while Pipelinq provides the user experience
3. **Shared URN namespace** -- Both could reference the same citizens/organizations via URN patterns
4. **Task delegation** -- Pipelinq pipeline steps could create GegevensUitvraagTaken or FormulierTaken for citizen self-service

---

## Conclusion

Open VTB is **not a direct competitor** to Pipelinq. It is a **backend API register** that stores structured data about government requests, tasks, and messages following Dutch government standards. It lacks any user-facing interface.

Pipelinq provides what Open VTB lacks: a **visual frontend**, **workflow engine**, and **user experience** within Nextcloud. The opportunity is to potentially use Open VTB's well-designed data models and API patterns as inspiration for Pipelinq's own data structures, or to integrate with Open VTB as a backend data source.

### Key Takeaways for Pipelinq Development

1. **Adopt URN patterns** for cross-system references instead of internal IDs
2. **Consider typed request schemas** with JSON Schema validation for structured data intake
3. **Polymorphic task types** (payment, form, data request) are a valuable pattern
4. **Versioned type definitions** allow evolving forms without breaking existing submissions
5. **Audit-trail messaging** (create-only, no delete) is important for government compliance
6. **Automatic reminder calculation** from deadlines is a useful UX feature
7. **Separate API specs per component** improves API documentation quality
