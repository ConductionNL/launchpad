---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
---

# URN Addressing System

## Purpose

Open VTB uses RFC 8141-compliant URNs (Uniform Resource Names) as the primary cross-system referencing mechanism. Instead of URLs or foreign keys, entities reference external objects (persons, cases, documents, products) via URN strings. This enables loose coupling between VTB and other government systems (BRP, HR, zaakregistratie, etc.).

## Architecture

### URN Format
`urn:<namespace>:<component>:<resource>:<identifier>`

Examples:
- `urn:nld:brp:bsn:111222333` (citizen by BSN)
- `urn:nld:hr:kvknummer:444555666` (company by KvK number)
- `urn:nld:gemeenteutrecht:zaak:zaaknummer:000350165` (case)
- `urn:nld:gemeenteutrecht:informatieobject:uuid:717815f6-...` (document)
- `urn:nld:klant:klantnummer:610541501` (customer)

### Implementation
- **URNField** (model field): CharField(255) with RFC 8141 regex validation
- **URNValidator**: Full ABNF-based regex covering NID, NSS, r/q components, f-component
- **URNRelatedField** (serializer field): Resolves URNs to model instances (for internal refs)
- **URNIdentityField**: Read-only field exposing object's own URN
- **URNModelSerializer**: Base serializer adding automatic URN field to all models
- **Configurable namespace**: `URN_NAMESPACE` env var sets the organization prefix

### Where URNs are used
| Entity | Field | References |
|---|---|---|
| Verzoek | initiator | Person/org who submitted |
| Verzoek | is_gerelateerd_aan | Related cases/products |
| Verzoek | verzoek_informatie_object | Request as document |
| ExterneTaak | is_toegewezen_aan | Assigned citizen/org |
| ExterneTaak | wordt_behandeld_door | Handling employee |
| ExterneTaak | hoort_bij | Parent case (ZAAK) |
| ExterneTaak | heeft_betrekking_op | Related product |
| Bericht | ontvanger | Message recipient |
| Bijlage (all) | informatie_object | Referenced document |
| BijlageType | informatie_objecttype | Referenced document type |

## Pipelinq Comparison

### Already in Pipelinq
- UUID-based object identification
- Object relations in OpenRegister

### Not yet in Pipelinq
- **RFC 8141 URN addressing** for cross-system references
- **Configurable namespace** per deployment
- **URN validation** with full ABNF regex
- **Auto-generated URN identity** on all API responses
- **URN-based person/org referencing** (BSN, KvK integration points)
