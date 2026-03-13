# VNG Standards -- Klantinteracties & Related APIs

## Overview of API Evolution

The VNG (Vereniging van Nederlandse Gemeenten) has evolved its customer interaction APIs through two generations:

### Generation 1: Klanten + Contactmomenten APIs (DEPRECATED)

These APIs were part of the "API's voor Zaakgericht Werken" standard. They are now marked as "niet meer in gebruik" (no longer in use).

**Klanten API** (v1):
- Source: https://vng-realisatie.github.io/gemma-zaken/standaard/klanten/index
- GitHub: https://github.com/VNG-Realisatie/klanten-api
- Reference implementation: klanten-api.vng.cloud
- Purpose: Storage and disclosure of customer data
- A "Klant" is a natural person, optionally acting as employee or representative of a legal entity
- Key validation: bronorganisatie + klantnummer must be unique (kla-001)
- Subject URL validation on creation (kla-002)
- Customer roles: Belanghebbende (stakeholder), Gesprekspartner (conversation partner)

**Contactmomenten API** (v1):
- Source: https://vng-realisatie.github.io/gemma-zaken/standaard/contactmomenten/
- GitHub: https://github.com/VNG-Realisatie/contactmomenten-api
- Reference implementation: contactmomenten-api.vng.cloud
- Purpose: Storage of contact moments and related metadata
- Resources: Contactmoment, Klantcontactmoment, Objectcontactmoment
- Sequential linking via vorigContactmoment/volgendContactmoment
- Relationship with cases via zaakcontactmoment/objectcontactmoment
- Relationship with customers via klantcontactmoment
- Behavioral rules: cm-001 through cm-006 (URL validation, auto-population, uniqueness)
- ETag-based HTTP caching required

**Verzoeken API** (v1):
- Source: https://github.com/VNG-Realisatie/verzoeken-api
- A "verzoek" (request) bridges customer needs with municipal services
- Can result in one or more cases
- Supports attachments as informatieobjecten
- Synchronization rule (kic-001): relations must be mirrored in DRC

### Generation 2: Klantinteracties API (CURRENT -- but NOT yet a formal standard)

- Source: https://vng-realisatie.github.io/klantinteracties/
- GitHub: https://github.com/VNG-Realisatie/klantinteracties
- **Status**: NOT established as a standard. Published as a "halfproduct" under EUPL. Active development paused as of July 2024.
- Materials are "as is, where is" -- explicitly stated as inspiration sources, NOT for production use directly

**Components:**
1. **Semantic Information Model (SIM)** -- developed iteratively with municipalities and suppliers
2. **Data Dictionary (Gegevenswoordenboek)**
3. **Base Terminology (Basisterminologie)**
4. **Product Vision (Productvisie)**
5. **Architecture** -- design considerations for API specifications
6. **Principles (Uitgangspunten)**
7. **Use Cases (Cases)**
8. **Functions (Functies)**
9. **Questions and Decisions (Vragen en besluiten)**
10. **API Design Patterns** -- examples showing how the model could be translated into specifications

**Critical note**: The VNG spec explicitly states that API specifications are NOT developed for practical use, only as inspiration. Open Klant's implementation (v0.6.0) is Maykin Media's practical interpretation of this model.

## Open Klant's Position

Open Klant v2.x implements the Klantinteracties API based on the VNG semantic model, but since the VNG specification is NOT a formal standard:

1. Open Klant is effectively the **de facto standard** implementation
2. The specification version (0.6.0) indicates it is still evolving
3. Municipalities like Amsterdam, Den Haag, and Utrecht use Open Klant as their implementation
4. KISS (Klantinteractie Servicesysteem) depends on Open Klant for its backend

## Implications for Pipelinq

The VNG Klantinteracties specification is NOT a finalized standard, which means:
- There is no formal compliance requirement (unlike ZGW APIs for zaakgericht werken)
- Open Klant has de facto defined the API surface through its implementation
- A competing implementation could choose different API patterns while following the same semantic model
- The key value is in the **data model** (Partij, Klantcontact, etc.), not the specific API paths
- Pipelinq could implement a compatible API or use Open Klant as a data source
