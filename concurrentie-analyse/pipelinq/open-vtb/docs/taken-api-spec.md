# Taken API v0.1.0 — OpenAPI Specification Summary

Source: `src/openvtb/components/taken/openapi.yaml`

## Info

- **Title**: Taken API
- **Version**: 0.1.0
- **License**: EUPL 1.2
- **Contact**: VNG (standaarden.ondersteuning@vng.nl)
- **Base URL**: `/taken/api/v1`

## Description

The Taken-Service defines and manages citizen tasks. It enables consistent communication between applications using standardized URN patterns. Tasks are actions assigned by a government case handler (ZAC) to citizens or businesses, displayed in citizen portals.

## Paths

### ExterneTaken (All External Tasks)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/externetaken` | externetakenList | List all tasks |
| POST | `/externetaken` | externetakenCreate | Create task |
| GET | `/externetaken/{uuid}` | externetakenRetrieve | Retrieve task |
| PUT | `/externetaken/{uuid}` | externetakenUpdate | Full update |
| PATCH | `/externetaken/{uuid}` | externetakenPartialUpdate | Partial update |
| DELETE | `/externetaken/{uuid}` | externetakenDestroy | Delete task |

### Betaaltaken (Payment Tasks)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/betaaltaken` | betaaltakenList | List payment tasks |
| POST | `/betaaltaken` | betaaltakenCreate | Create payment task |
| GET | `/betaaltaken/{uuid}` | betaaltakenRetrieve | Retrieve |
| PUT | `/betaaltaken/{uuid}` | betaaltakenUpdate | Full update |
| PATCH | `/betaaltaken/{uuid}` | betaaltakenPartialUpdate | Partial update |
| DELETE | `/betaaltaken/{uuid}` | betaaltakenDestroy | Delete |

### Formuliertaken (Form Tasks)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/formuliertaken` | formuliertakenList | List form tasks |
| POST | `/formuliertaken` | formuliertakenCreate | Create form task |
| GET | `/formuliertaken/{uuid}` | formuliertakenRetrieve | Retrieve |
| PUT | `/formuliertaken/{uuid}` | formuliertakenUpdate | Full update |
| PATCH | `/formuliertaken/{uuid}` | formuliertakenPartialUpdate | Partial update |
| DELETE | `/formuliertaken/{uuid}` | formuliertakenDestroy | Delete |

### Gegevensuitvraagtaken (Data Request Tasks)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/gegevensuitvraagtaken` | gegevensuitvraagtakenList | List data request tasks |
| POST | `/gegevensuitvraagtaken` | gegevensuitvraagtakenCreate | Create data request task |
| GET | `/gegevensuitvraagtaken/{uuid}` | gegevensuitvraagtakenRetrieve | Retrieve |
| PUT | `/gegevensuitvraagtaken/{uuid}` | gegevensuitvraagtakenUpdate | Full update |
| PATCH | `/gegevensuitvraagtaken/{uuid}` | gegevensuitvraagtakenPartialUpdate | Partial update |
| DELETE | `/gegevensuitvraagtaken/{uuid}` | gegevensuitvraagtakenDestroy | Delete |

## Schemas

### Common Task Properties (ExterneTaak)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| uuid | string (uuid) | read-only | UUID4 identifier |
| titel | string (max 100) | Yes | Task title for end users |
| status | enum | No | open / uitgevoerd / niet_uitgevoerd / afgebroken / verwerkt |
| startdatum | date | No | Start date (default: today) |
| einddatumHandelingsTermijn | date | Yes | Action deadline |
| datumHerinnering | date | No (nullable) | Reminder notification date (auto-calculated) |
| toelichting | string (max 4000) | No | Task explanation/description |
| handelingsPerspectief | string (max 100) | No | Expected action (lezen, naleveren, invullen) |
| isToegewezenAan | string (urn) | No | Assigned person/org URN |
| wordtBehandeldDoor | string (urn) | No | Handling employee URN |
| hoortBij | string (urn) | No | Related case (ZAAK) URN |
| heeftBetrekkingOp | string (urn) | No | Related product URN |

### BetaalTaak Details

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| bedrag | string (decimal) | Yes | Payment amount |
| valuta | string | Yes | Currency code (EUR default) |
| transactieomschrijving | string (max 80) | Yes | Transaction description |
| doelrekening | Doelrekening | Yes | Target bank account |

### Doelrekening (Target Account)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| naam | string (max 200) | No* | Account holder name |
| code | string (max 100) | No* | Arbitrary payment code |
| iban | string (max 34) | No* | IBAN number |

*At least one of naam/code/iban is required (anyOf constraint).

### FormulierTaak Details

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| formulierDefinitie | object | Yes | FormIO-compatible form definition |
| voorinvullenGegevens | object | No | Pre-fill data |
| ontvangenGegevens | object | No | Received form data |

### GegevensUitvraagTaak Details

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| uitvraagLink | string (uri) | Yes | External form URL |
| voorinvullenGegevens | object | No | Pre-fill data |
| ontvangenGegevens | object | No | Received data |

## Status Lifecycle

```
open -> uitgevoerd (completed by citizen)
open -> niet_uitgevoerd (not completed)
open -> afgebroken (cancelled)
uitgevoerd -> verwerkt (processed by case handler)
```

## Task Type (taak_soort) Values

| Value | Label | Description |
|-------|-------|-------------|
| betaaltaak | Betaallink | Payment link task |
| gegevensuitvraagtaak | Extern formulier | External form task |
| formuliertaak | Standaard formulier | Standard embedded form task |

## Pagination

| Parameter | Type | Default | Max |
|-----------|------|---------|-----|
| page | integer | 1 | - |
| pageSize | integer | 100 | 500 |

## Security

- **OpenID Connect**: `openIdConnect` type
- **Token Authentication**: API key in Authorization header with "Token" prefix
