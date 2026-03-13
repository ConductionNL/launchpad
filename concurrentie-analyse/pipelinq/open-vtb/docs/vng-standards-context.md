# VNG API Standards Context for Open VTB

## Overview of the VNG ZGW API Landscape

The VNG (Vereniging Nederlandse Gemeenten) maintains API standards for "zaakgericht werken" (case-based working). Open VTB positions itself within this landscape but its relationship to the formal standards is complicated.

## Active ZGW API Standards (as of March 2026)

| API | Current Version | Status |
|-----|----------------|--------|
| Zaken API | 1.5.1 (concept: 1.6.0) | Active |
| Catalogi API | 1.3.1 (concept: 1.3.2) | Active |
| Documenten API | 1.5.0 (concept: 1.6.0) | Active |
| Besluiten API | 1.0.2 (concept: 1.1.0) | Active |
| Autorisaties API | 1.0.0 | Active |
| Notificaties API | 1.0.0 (concept: 1.0.1) | Active |

## Deprecated/Removed ZGW APIs

| API | Status | Reason |
|-----|--------|--------|
| **Verzoeken API** | Archived (June 2023) | Moved to Klantinteracties |
| **Contactmomenten API** | Removed | Moved to Klantinteracties |
| **Klanten API** | Removed | Moved to Klantinteracties |

## VNG Verzoeken API (Archived)

The original VNG Verzoeken API was:
- Developed by Maykin Media as reference implementation
- Part of the ZGW standard set
- Archived on GitHub in June 2023
- 93 commits, Python 3.7+

### Original VNG Verzoeken API Resources

1. **Verzoek** (Request)
2. **Klantverzoek** (Customer-Request relation)
3. **Verzoekcontactmoment** (Request-Contact moment relation)
4. **Verzoekinformatieobject** (Request-Document relation)
5. **Verzoekproduct** (Request-Product relation)
6. **Objectverzoek** (Object-Request relation)

### Key Requirements from VNG Spec

- **vrz-001**: Unique combination of `identificatie` + `bronorganisatie`
- **vrz-002/003**: Bidirectional relationship management for withdrawals/supplements
- **vrz-004 through vrz-008**: URL reference validation (HTTP 200 check)
- **vrz-009**: Source system verification for object-request relationships
- **HTTP Caching**: ETag-based, with HTTP 304 support
- **HEAD requests**: Must return identical headers without body

### Differences: VNG Verzoeken API vs Open VTB Verzoeken API

| Aspect | VNG Verzoeken API (archived) | Open VTB Verzoeken API |
|--------|------------------------------|----------------------|
| Version | master (no formal release) | v0.1.0 |
| Resources | 6 (verzoek + 5 relation types) | 4 (verzoek, verzoektype, versie, bijlage) |
| Identification | identificatie + bronorganisatie | UUID + URN |
| Cross-referencing | URL-based with validation | URN-based (no validation) |
| Schema validation | None (free-form) | JSON Schema per VerzoekType |
| Versioning | None | VerzoekType versions with lifecycle |
| Payment support | None | VerzoekBetaling model |
| Geo support | None | PostGIS geometry |
| Caching | ETag required | Not specified |

Open VTB is NOT a direct implementation of the archived VNG Verzoeken API. It is a redesigned, more opinionated take on the same concept.

## Klantinteracties (Replacement Initiative)

The VNG Klantinteracties project was intended to replace the Verzoeken, Contactmomenten, and Klanten APIs with a unified specification.

### Current Status (March 2026)

- **Not a standard**: Explicitly stated as "not established as standard"
- **Not recommended**: "Not recommended or compulsory to use"
- **No active maintenance**: As of July 2024
- **Stalled**: Other priorities have prevented practical testing

### Klantinteracties Components

1. **Semantic Information Model**: Considered the most useful output, suitable as foundation
2. **Supporting Documentation**: Terminology, principles, use cases
3. **API Specifications**: Explicitly "unsuitable for production use", intended as "inspiration"

### Implication for Open VTB

Since the Klantinteracties initiative has stalled:
- There is no successor standard for the Verzoeken API
- Open VTB fills this vacuum with its own specification
- There is a risk the standards landscape changes if Klantinteracties is revived
- There is also an opportunity: if VNG never finalizes Klantinteracties, Open VTB's spec could become the de facto standard

## Taken & Berichten: No VNG Standard

Unlike Verzoeken (which had a VNG standard, albeit archived), there are no VNG standards for:

- **Taken API**: Open VTB defines its own task management API
- **Berichten API**: Open VTB defines its own messaging API

The closest VNG concept is the "Contactmoment" from the (now-deprecated) Contactmomenten API, but this was about logging interactions, not managing tasks or sending messages.

## Open Klant: The Active Successor

Maykin's Open Klant (https://github.com/maykinmedia/open-klant) implements the Klantinteracties API and is more actively developed than Open VTB. Open Klant includes concepts like:
- **Partij** (Party): Customer/organization
- **Interne Taak** (Internal Task): Task assigned within the organization
- **Contactmoment**: Interaction logging

The "Interne Taak" in Open Klant overlaps with Open VTB's "Externe Taak" but targets different audiences:
- Open Klant Interne Taak: assigned to municipal employees
- Open VTB Externe Taak: assigned to citizens/businesses

## Standards Compliance Summary

| Open VTB Component | VNG Standard | Compliance |
|--------------------|-------------|------------|
| Verzoeken API | Verzoeken API (archived) | Inspired by, not compliant with |
| Taken API | None | De facto self-defined |
| Berichten API | None | De facto self-defined |
| URN Addressing | RFC 8141 | Compliant |
| Authentication | NL Gov standards | OIDC + Token (standard patterns) |
