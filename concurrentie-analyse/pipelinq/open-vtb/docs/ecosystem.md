# Open VTB Ecosystem & Integration Analysis

## Maykin Media Product Portfolio

Maykin Media B.V. is a Dutch Python/Django web development company that builds open-source Common Ground components for municipalities. Open VTB is one of many products in their portfolio:

### Core Products (Mature, Production-Ready)

| Product | Purpose | Maturity |
|---------|---------|----------|
| **Open Zaak** | Case management (zaakregistratie) | Production, widely adopted |
| **Open Formulieren** | Form builder and submission | Production, widely adopted |
| **Open Notificaties** | Event notification service | Production |
| **Open Inwoner** | Citizen portal (Mijn omgeving) | Production, 6+ municipalities |
| **Open Klant** | Customer/contact registry | Production |
| **Objects API** | Generic object storage | Production |

### Newer Products (Active Development)

| Product | Purpose | Maturity |
|---------|---------|----------|
| **Open Product** | Product catalog management | Active development |
| **Open Archiefbeheer** | Archive management | Active development |
| **Open VTB** | Requests, tasks, messages | Early-stage (v0.1.0) |

## Integration Diagram

```
Citizen/Business
    |
    v
+-------------------+     +------------------+
| Open Formulieren  | --> | Open VTB         |
| (form submission) |     | (Verzoeken API)  |
+-------------------+     +--------+---------+
                                   |
                          Creates verzoek with
                          initiator URN +
                          aanvraagGegevens
                                   |
                                   v
                          +--------+---------+
                          | Case Handler     |
                          | (ZAC / Open Zaak)|
                          +--------+---------+
                                   |
                    +--------------+--------------+
                    |                             |
                    v                             v
           +-------+--------+           +--------+--------+
           | Open VTB       |           | Open VTB        |
           | (Taken API)    |           | (Berichten API) |
           +-------+--------+           +--------+--------+
                   |                              |
                   v                              v
           +-------+--------+           +--------+--------+
           | Open Inwoner   |           | Open Inwoner    |
           | (Mijn taken)   |           | (Mijn berichten)|
           +----------------+           +--------+--------+
                                                  |
                                                  v
                                        +--------+--------+
                                        | Mijn Overheid   |
                                        | (berichtenbox)  |
                                        +-----------------+
```

## URN-Based Loose Coupling

Open VTB does NOT directly integrate with other systems. Instead, it uses URN references:

| URN Pattern | Points to | System |
|-------------|-----------|--------|
| `urn:nld:brp:bsn:*` | Citizen (BSN number) | BRP (civil registry) |
| `urn:nld:hr:kvknummer:*` | Business (KvK number) | Handelsregister |
| `urn:nld:*:zaak:*` | Case | Open Zaak |
| `urn:nld:*:informatieobject:*` | Document | Documenten API |
| `urn:nld:*:product:*` | Product | Open Product |
| `urn:nld:klant:klantnummer:*` | Customer | Open Klant |

This means VTB does not validate that these references actually exist -- it stores URN strings and trusts the consuming application to resolve them.

## Known Adopters / Deployments

**No known production deployments have been identified.** The project was developed for the "Platform Dienstverlening werkgroep" but no municipalities are known to be running it.

### Contrasted with Open Zaak Adoption

For comparison, Open Zaak (Maykin's most successful product) is used by:
- Amsterdam, Rotterdam, Utrecht, Groningen (G4)
- Dimpact municipalities (Deventer, Enschede, Zwolle, etc.)
- 60+ municipalities total

Open VTB has zero known production users.

## Open Inwoner Integration

Open Inwoner is the natural front-end consumer of Open VTB data:

- **Mijn Berichten**: Displays messages from the Berichten API
- **Mijn Taken**: Could display tasks from the Taken API
- **Mijn Aanvragen**: Could display request status from the Verzoeken API

Open Inwoner is deployed by Deventer, Enschede, Groningen, Leeuwarden, Hoorn, and Zwolle.

However, it is unclear whether Open Inwoner currently integrates with Open VTB or uses its own internal messaging system. The Open Inwoner inbox ("Mijn berichten") existed before Open VTB.

## Competitive Landscape

### Direct Competitors for VTB's Scope

| Competitor | Scope | Maturity |
|-----------|-------|----------|
| **Pipelinq** (Conduction) | CRM + pipeline + tasks | Active development |
| **ZAC** (Dimpact/Info.nl) | Case handling + tasks | Production |
| **e-Suite** (Atos/Centric) | Full stack (proprietary) | Production, legacy |
| **RX.Mission** (Roxit) | Full service delivery | Production |

### Open VTB's Unique Position

Open VTB is the only open-source component that specifically addresses:
1. The "verzoek" concept as separate from a "zaak" (case)
2. External task assignment back to citizens
3. Government-to-citizen messaging with Mijn Overheid routing

This is a narrow scope that most competitors handle as part of a larger integrated system rather than as a standalone registry.

## VNG Standards Context

### Deprecated Standards

The VNG Verzoeken API (https://github.com/VNG-Realisatie/verzoeken-api) was **archived in June 2023**. The reference implementation was developed by Maykin Media itself. The API was part of the ZGW standard set but was moved to the Klantinteracties initiative.

### Klantinteracties: Stalled

The VNG Klantinteracties project (https://vng-realisatie.github.io/klantinteracties/) is explicitly described as:
- "Not established as standard"
- "Not recommended or compulsory to use"
- "A freely usable half-product"
- "No active maintenance as of July 2024"

The Klantinteracties initiative was supposed to replace the Verzoeken, Contactmomenten, and Klanten APIs with a unified specification, but this work has stalled.

### Implications for Open VTB

Open VTB is building on standards that are either:
1. **Deprecated** (Verzoeken API - archived 2023)
2. **Never standardized** (Taken API, Berichten API)
3. **Stalled** (Klantinteracties replacement)

This creates a risk: Open VTB may implement APIs that never achieve formal VNG standardization, limiting its interoperability value.
