---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
---

# Betaaltaak (Payment Task)

## Purpose

A specialized task type for outstanding payments. The government creates a betaaltaak to request payment from a citizen, specifying the amount, currency, transaction description, and target bank account. The task is displayed in the citizen portal with a payment link.

## Data Model

The `details` field of an ExterneTaak with `taak_soort = "betaaltaak"` must conform to:

| Field | Type | Required | Description |
|---|---|---|---|
| bedrag | string (decimal) | Yes | Amount to pay (max 2 decimal places) |
| valuta | string | Yes | Currency code (EUR only, ISO 4217) |
| transactieomschrijving | string(80) | Yes | Transaction description |
| doelrekening | object | Yes | Target bank account |
| doelrekening.naam | string(200) | No* | Account holder name |
| doelrekening.code | string(100) | No* | Arbitrary payment code |
| doelrekening.iban | string (IBAN) | No* | IBAN number |

*At least one of naam/code/iban is required (anyOf constraint).

## API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/taken/api/v1/betaaltaken/` | List payment tasks |
| POST | `/taken/api/v1/betaaltaken/` | Create payment task |
| GET | `/taken/api/v1/betaaltaken/{uuid}/` | Retrieve |
| PUT | `/taken/api/v1/betaaltaken/{uuid}/` | Full update |
| PATCH | `/taken/api/v1/betaaltaken/{uuid}/` | Partial update |
| DELETE | `/taken/api/v1/betaaltaken/{uuid}/` | Delete |

### Validation
- `valuta` must be "EUR" (other currencies rejected)
- `bedrag` validated as decimal with max 2 decimal places
- `doelrekening.iban` validated against IBAN regex `^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{1,30}$`
- `doelrekening` must have at least one of: iban, code, or naam

## Pipelinq Comparison

### Already in Pipelinq
- None (no payment task concept)

### Not yet in Pipelinq
- **Payment task type** with structured amount/currency/account data
- **IBAN validation**
- **Payment provider integration points** (doelrekening.code)
- **Transaction description** for bank statements
