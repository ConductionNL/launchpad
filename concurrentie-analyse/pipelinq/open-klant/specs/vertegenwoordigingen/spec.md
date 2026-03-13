---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Vertegenwoordigingen (Representation) -- Open Klant

## Purpose

Models which parties represent other parties. For example, a legal representative acting on behalf of a citizen, or a parent representing a child.

- **Product**: Open Klant
- **Category**: Legal Representation
- **Relevance to Pipelinq**: Important for government services where authorized representatives act on behalf of others.

## Data Model

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| vertegenwoordigende_partij | FK -> Partij | The party doing the representing |
| vertegenwoordigde_partij | FK -> Partij | The party being represented |

### Constraints

- `unique_together`: (vertegenwoordigende_partij, vertegenwoordigde_partij)
- **Self-representation not allowed**: Validation rejects if both FKs point to the same Partij

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/vertegenwoordigingen/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/vertegenwoordigingen/{uuid}/` | Detail CRUD | Token |

### Filters

- `vertegenwoordigende_partij__uuid`, `vertegenwoordigende_partij__url`
- `vertegenwoordigde_partij__uuid`, `vertegenwoordigde_partij__url`

## Pipelinq Comparison

**Already in Pipelinq**: None
**Not yet in Pipelinq**: Legal representation tracking between parties
