---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Rekeningnummers (Bank Accounts) -- Open Klant

## Purpose

Stores IBAN/BIC bank account numbers for parties. Each party can have multiple bank accounts with one designated as preferred.

- **Product**: Open Klant
- **Category**: Financial Data Management
- **Relevance to Pipelinq**: Payment/reimbursement capability for client management.

## Data Model

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| partij | FK -> Partij (nullable) | |
| iban | CharField(34) | IBAN (validated) |
| bic | CharField(11) | BIC code (min 8 chars, no spaces) |

### Validation

- IBAN: Custom `validate_iban` validator
- BIC: MinLengthValidator(8) + `validate_no_space`

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/rekeningnummers/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/rekeningnummers/{uuid}/` | Detail CRUD | Token |

### Filters

- `uuid`, `iban`, `bic`

## Pipelinq Comparison

**Already in Pipelinq**: None
**Not yet in Pipelinq**: Bank account management with IBAN/BIC validation, preferred account designation
