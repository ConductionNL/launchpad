---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Decision (Besluit) Management -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements formal decision management.
- **Product**: Dimpact ZAC
- **Category**: Decision Management
- **Relevance to Procest**: Formal decisions are a key output of government case processing

## Architecture Overview
Decisions are stored in the ZGW BRC (Besluiten Registratie Component). ZAC provides creation, modification, withdrawal, and document linking. Decision types come from the ZTC catalog.

Key service: `DecisionService`

## Data Model

### RestDecisionCreateData
| Field | Type | Description |
|-------|------|-------------|
| besluittypeUuid | UUID | Decision type reference |
| toelichting | String | Explanation |
| ingangsdatum | LocalDate | Effective date |
| vervaldatum | LocalDate | Expiration date |
| publicationDate | LocalDate | Publication date |
| lastResponseDate | LocalDate | Last response date |
| informatieobjecten | List<UUID> | Linked document UUIDs |

### Decision Type Properties (from ZTC)
- publicatieIndicatie -- whether publication is required
- reactietermijn -- response period (ISO 8601 duration)

## Business Logic

### Creation
- Requires: zaak open + not in intake + zaaktype has besluittypen
- Validates publication dates if publicatieIndicatie is true
- Validates response date is after publication date + reaction period
- Creates besluit in BRC, links informatieobjecten

### Modification
- Re-validates publication dates
- Syncs informatieobjecten: adds new, removes unlinked (CollectionUtils.subtract)

### Withdrawal
- Sets vervaldatum and vervalreden
- Two withdrawal reasons: INGETROKKEN_OVERHEID, INGETROKKEN_BELANGHEBBENDE
- Formats explanation with reason prefix

### Publication Date Validation Rules
1. If besluittype has no publicatieIndicatie: no dates allowed
2. If publication date set: response date required
3. If response date set: publication date required
4. Response date must be >= publication date + reactietermijn

## Requirements (as observed)

1. Decisions require a zaak in "In behandeling" phase (not intake)
2. Publication/response dates are validated against the besluittype's reaction period
3. Document links are managed as BesluitInformatieObject records
4. Withdrawal has two formal reasons matching legal categories
5. A zaak with decisions cannot be terminated (only closed properly)

## Comparison Notes
- ZAC's decision model maps directly to the BRC API standard
- The publication date validation with reaction period calculation is specific to Dutch administrative law
- Procest should implement similar validation if targeting government use cases
