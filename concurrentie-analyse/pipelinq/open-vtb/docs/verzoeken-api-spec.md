# Verzoeken API v0.1.0 — OpenAPI Specification Summary

Source: `src/openvtb/components/verzoeken/openapi.yaml`

## Info

- **Title**: Verzoeken API
- **Version**: 0.1.0
- **License**: EUPL 1.2
- **Contact**: VNG (standaarden.ondersteuning@vng.nl)
- **Base URL**: `/verzoeken/api/v1`

## Description

The Verzoeken API manages form submissions as structured JSON data. Forms define JSON Schemas for validation of incoming structured data.

## Paths

### Verzoeken (Requests)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/verzoeken` | verzoekenList | List all requests (paginated) |
| POST | `/verzoeken` | verzoekenCreate | Create new request |
| GET | `/verzoeken/{uuid}` | verzoekenRetrieve | Retrieve specific request |
| PUT | `/verzoeken/{uuid}` | verzoekenUpdate | Full update |
| PATCH | `/verzoeken/{uuid}` | verzoekenPartialUpdate | Partial update |
| DELETE | `/verzoeken/{uuid}` | verzoekenDestroy | Delete request |

### VerzoekTypen (Request Types)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/verzoektypen` | verzoektypenList | List all request types |
| POST | `/verzoektypen` | verzoektypenCreate | Create request type |
| GET | `/verzoektypen/{uuid}` | verzoektypenRetrieve | Retrieve request type |
| PUT | `/verzoektypen/{uuid}` | verzoektypenUpdate | Update request type |
| PATCH | `/verzoektypen/{uuid}` | verzoektypenPartialUpdate | Partial update |
| DELETE | `/verzoektypen/{uuid}` | verzoektypenDestroy | Delete request type |

### VerzoekType Versies (Request Type Versions)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/verzoektypen/{uuid}/versies` | versiesList | List versions |
| POST | `/verzoektypen/{uuid}/versies` | versiesCreate | Create version |
| GET | `/verzoektypen/{uuid}/versies/{versie}` | versiesRetrieve | Retrieve version |
| PUT | `/verzoektypen/{uuid}/versies/{versie}` | versiesUpdate | Update version |
| PATCH | `/verzoektypen/{uuid}/versies/{versie}` | versiesPartialUpdate | Partial update |
| DELETE | `/verzoektypen/{uuid}/versies/{versie}` | versiesDestroy | Delete version |

## Schemas

### Verzoek

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| url | string (uri) | read-only | Unique URL within this API (max 1000) |
| urn | string (urn) | read-only | Uniform Resource Name |
| uuid | string (uuid) | read-only | UUID4 identifier |
| verzoekType | string (uri) | Yes | Reference to the request type |
| aanvraagGegevens | object | Yes | Request data (validated against JSON Schema) |
| geometrie | GeoJSON | No | Point/LineString/Polygon location |
| versie | integer | No | Schema version (defaults to latest) |
| bijlagen | array[Bijlage] | No | Attachments |
| initiator | string (urn) | No | URN to person/organization (BSN, KvK) |
| isGerelateerdAan | array[IsGerelateerdAan] | No | Related case/product URNs |
| kanaal | string (max 200) | No | Submission channel |
| verzoekTaal | string (max 2) | No | Language code (default "nl") |
| verzoekInformatieObject | string (urn) | No | URN to request document |
| verzoekBron | VerzoekBron | No | Source application info |
| verzoekBetaling | VerzoekBetaling | No | Payment information |

### VerzoekType

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| uuid | string (uuid) | read-only | UUID4 identifier |
| urn | string (urn) | read-only | Uniform Resource Name |
| url | string (uri) | read-only | Unique URL |
| naam | string (max 100) | Yes | Name of request type |
| omschrijving | string (max 4000) | No | Internal description |
| versies | array | read-only | Array of versions |

### VerzoekTypeVersion

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| versie | integer | read-only | Auto-incremented version number |
| verzoekType | string (uri) | read-only | Parent type reference |
| bijlageTypen | array[BijlageType] | No | Expected attachment types |
| status | enum | No | published / draft / deprecated |
| aanvraagGegevensSchema | object | No | JSON Schema for validation |
| aangemaaktOp | date | read-only | Created date |
| gewijzigdOp | date | read-only | Modified date |
| beginGeldigheid | date | No | Validity start |
| eindeGeldigheid | date | No | Validity end |

### Bijlage (Attachment)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| informatieObject | string (urn) | Yes | URN to document |

### BijlageType

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| informatieObjecttype | string (urn) | Yes | URN to document type |
| omschrijving | string | No | Description |

### VerzoekBron (Source)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| naam | string (max 100) | No | Source application name |
| kenmerk | string (max 255) | No | Submission reference ID |

### VerzoekBetaling (Payment)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| providerKenmerk | string (max 100) | No | Payment provider ID |
| bedrag | decimal | No (nullable) | Payment amount |
| voltooid | boolean | No | Payment completed |
| transactieDatum | datetime | No (nullable) | Transaction timestamp |
| transactieReferentie | string (max 100) | No | Provider transaction reference |

### IsGerelateerdAan

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| urn | string (urn) | Yes | Related case or product URN |

## Pagination

| Parameter | Type | Default | Max |
|-----------|------|---------|-----|
| page | integer | 1 | - |
| pageSize | integer | 100 | 500 |

## Security

- **OpenID Connect**: `openIdConnect` type
- **Token Authentication**: API key in Authorization header with "Token" prefix

## GeoJSON Support

Supported geometry types: Point, MultiPoint, LineString, MultiLineString, Polygon, MultiPolygon, GeometryCollection.
