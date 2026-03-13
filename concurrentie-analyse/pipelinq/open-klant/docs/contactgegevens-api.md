# Contactgegevens API -- Complete Specification

**Source**: OpenAPI spec at `src/openklant/components/contactgegevens/openapi.yaml`
**Version**: 1.1.1
**Base Path**: `/contactgegevens/api/v1/`
**Authentication**: Token-based (`Authorization: Token <token>`)

## Overview

The Contactgegevens API is a separate, simpler API for basic person and organisation contact details. Unlike the Klantinteracties API, this API is NOT based on a VNG standard -- it is a custom API developed by Maykin Media.

## Endpoints

### Organisatie (Organisation)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/organisatie` | List all organisations (paginated) |
| POST | `/organisatie` | Create organisation |
| GET | `/organisatie/{uuid}` | Get organisation |
| PUT | `/organisatie/{uuid}` | Full update (all mandatory fields required) |
| PATCH | `/organisatie/{uuid}` | Partial update |
| DELETE | `/organisatie/{uuid}` | Delete |

**Organisatie fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID | read-only | Technical identifier |
| url | URI | read-only | API URL |
| handelsnaam | string (max 255) | Yes | Business/location name |
| oprichtingsdatum | date (nullable) | No | Establishment date |
| opheffingsdatum | date (nullable) | No | Closure date |
| adres | OrganisatieAdres (nullable) | No | Address |

### Persoon (Person)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/persoon` | List all persons (paginated) |
| POST | `/persoon` | Create person |
| GET | `/persoon/{uuid}` | Get person |
| PUT | `/persoon/{uuid}` | Full update (all mandatory fields required) |
| PATCH | `/persoon/{uuid}` | Partial update |
| DELETE | `/persoon/{uuid}` | Delete |

**Persoon fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID | read-only | Technical identifier |
| url | URI | read-only | API URL |
| geboortedatum | date | Yes | Birth date |
| overlijdensdatum | date (nullable) | No | Death date |
| geslachtsnaam | string (max 200) | Yes | Family name |
| geslacht | enum/blank | No | "m", "v", "o", or blank |
| voorvoegsel | string (max 10) | No | Name prefix |
| voornamen | string (max 200) | No | Given names |
| adres | PersoonAdres (nullable) | No | Address |

## Address Model (shared)

Both OrganisatieAdres and PersoonAdres have identical structure:

| Field | Type | Description |
|-------|------|-------------|
| nummeraanduidingId | string | Pattern: `^[0-9]{16}$` (BAG ID) |
| straatnaam | string (max 255) | Street name |
| huisnummer | integer (1-99999, nullable) | House number |
| huisnummertoevoeging | string (max 20) | House number suffix |
| postcode | string | Pattern: `^[1-9][0-9]{3} [A-Z]{2}$` |
| stad | string (max 255) | City |
| adresregel1 | string (max 80) | Non-BAG address line 1 |
| adresregel2 | string (max 80) | Non-BAG address line 2 |
| adresregel3 | string (max 80) | Non-BAG address line 3 |
| land | string (2 chars) | ISO 3166 country code |

## Pagination

- Default: 100 results per page
- Maximum: 500 results per page
- Dynamic page size via `?pageSize=`

## Response Headers

All endpoints return `API-version` header (e.g. "1.2.1").
