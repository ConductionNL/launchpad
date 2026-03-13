# Open Klant — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (551 files), Documentation (Read the Docs + VNG), Browser walkthrough (23 screenshots)
**Verdict**: API-only klantinteracties backend — strong VNG standard positioning but no UI

---

## Executive Summary

Open Klant is Maykin Media's VNG Klantinteracties API reference implementation. It manages customers (partijen), contact moments (klantcontacten), and follow-up tasks (interne taken) as a headless Django REST API. No frontend — KISS or other apps provide the UI layer.

| Open Klant Concept | Pipelinq Equivalent |
|---|---|
| Partij (Persoon) | Client (person) |
| Partij (Organisatie) | Client (organization) |
| Partij (Contactpersoon) | Contact person (linked to org) |
| Klantcontact | Contact moment |
| Betrokkene | — (implicit in contact moment) |
| Actor (Medewerker) | — (Nextcloud user) |
| InterneTaak | My Work queue item |
| DigitaalAdres | Contact info (email, phone) |
| Rekeningnummer | — (not in Pipelinq) |
| Vertegenwoordiging | — (not in Pipelinq) |
| Categorie | — (tags/labels in Pipelinq) |

## Key Findings

### VNG Klantinteracties is NOT a formal standard
The VNG spec is published "as is" as inspiration — not a mandatory standard. Open Klant's implementation IS the de facto standard, used by 40+ municipalities via Dimpact.

### No RBAC
Any valid token gets full access. No per-resource permissions, no field-level auth.

### API-only, no UI
Django Admin only. KISS provides the frontend. This is the primary weakness vs Pipelinq.

### Adoption
Amsterdam, Den Haag, Utrecht, Rotterdam, and 40+ via Dimpact cooperative.

---

## Features Open Klant Has That Pipelinq ALREADY Has

| Feature | Open Klant | Pipelinq |
|---------|-----------|----------|
| Customer/person management | Partij (persoon) | Client management |
| Organization management | Partij (organisatie) | Organization management |
| Contact person linked to org | Partij (contactpersoon) | Contact persons |
| Contact moments logging | Klantcontact | Contact moments |
| Digital contact info | DigitaalAdres (email, phone) | Contact fields |
| Task follow-up | InterneTaak | My Work queue |
| Search/filter | API filters | Full-text + faceted search |
| Pagination | PageNumberPagination | Nextcloud pagination |
| Token auth | Custom TokenAuth | Nextcloud auth + API tokens |

## Features Open Klant Has That Pipelinq DOES NOT Have

| Feature | Description | Priority |
|---------|-------------|----------|
| **BRP/KvK identifier linking** | PartijIdentificator with BSN (11-proof), KvK, RSIN, Vestigingsnummer validation | HIGH |
| **Structured contact-to-task workflow** | Klantcontact → Betrokkene → InterneTaak with actor assignment | MEDIUM |
| **Actor model** | Medewerker / GeautomatiseerdeActor / OrganisatorischeEenheid | LOW (NC users suffice) |
| **Representation tracking** | Vertegenwoordiging — who represents whom (M:N) | MEDIUM |
| **Bank account management** | Rekeningnummer with IBAN/BIC | LOW |
| **Composite creation endpoint** | `/maak-klantcontact` — atomic party + contact + task in one call | MEDIUM |
| **VNG Klantinteracties API surface** | Standard API shape for government interop | HIGH |
| **CloudEvents zaak integration** | Emit events when contacts reference zaken | LOW |
| **Onderwerpobjecten** | Link contacts to external objects (zaken, producten) | MEDIUM |
| **Bijlagen** | Document reference attachments on contacts | LOW (we have files) |
| **Expand parameter** | `?expand=betrokkenen,partij` for eager loading | MEDIUM |

## Features Pipelinq Has That Open Klant LACKS

| Feature | Pipelinq | Open Klant |
|---------|----------|-----------|
| **Full Vue.js UI** | Complete CRM frontend | Django Admin only |
| **Lead pipeline (kanban)** | Visual drag-and-drop | Not available |
| **Request intake** | Web forms for leads | Not available |
| **Nextcloud Contacts sync** | Native CardDAV | Not available |
| **Duplicate detection** | Automated matching | Not available |
| **Import/Export (CSV/vCard)** | Bulk data management | Not available |
| **File attachments** | Native Nextcloud files | URL references only |
| **NL Design theming** | Government design system | Django Admin |
| **RBAC** | Nextcloud permission model | No RBAC (all-or-nothing) |
| **Case management integration** | Native Procest link | Via external ZGW APIs |
| **n8n workflow automation** | Built-in triggers | Via Celery/CloudEvents |

---

## Specs Created

### From Codebase (11 specs)
partijen, klantcontacten, actoren, interne-taken, digitale-adressen, rekeningnummers, vertegenwoordigingen, categorieen, contactgegevens, token-auth, maak-klantcontact, cloud-events

### From Documentation (15 specs)
partijen, klantcontacten, actoren, betrokkenen, onderwerpobjecten, bijlagen, digitale-adressen, interne-taken, cloud-events, maak-klantcontact, rekeningnummers, categorieen, vertegenwoordigingen, token-auth, contactgegevens

### From Browser (5 specs)
klantinteracties-api, contactgegevens-api, admin-interface, data-model, authentication

### Business Logic Diagrams (4)
klantcontact-lifecycle, partij-management, authentication-flow, data-model-relationships

### Screenshots (23)
All Django Admin pages, API browser, CRUD flows

### Documentation Archive (12 files)
RTD pages, VNG standards, changelog, ecosystem analysis

---

## Recommendations for Pipelinq

### Must-have (competitive parity with government clients)
1. **VNG Klantinteracties API compatibility** — similar to the OpenRegister VNG Objects API layer
2. **BRP/KvK identifier linking** — BSN, KvK, RSIN, Vestigingsnummer on clients

### Should-have
3. **Structured contact-to-task workflow** — formalize the contact moment → follow-up task flow
4. **Representation tracking** — who represents whom
5. **Composite creation endpoint** — create client + contact + task in one call

### Already winning on
- Full CRM UI, pipeline management, Nextcloud integration, file handling, search, import/export, duplicate detection, NL Design theming
