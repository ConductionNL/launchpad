# Berichten API v0.1.0 — OpenAPI Specification Summary

Source: `src/openvtb/components/berichten/openapi.yaml`

## Info

- **Title**: Berichten API
- **Version**: 0.1.0
- **License**: EUPL 1.2
- **Contact**: VNG (standaarden.ondersteuning@vng.nl)
- **Base URL**: `/berichten/api/v1`

## Description

The Berichten-service provides a standardized, flexible solution for communication between residents, entrepreneurs and municipalities via digital channels.

## Paths

### Berichten (Messages)

| Method | Path | Operation ID | Description |
|--------|------|-------------|-------------|
| GET | `/berichten` | berichtenList | List all messages (paginated) |
| POST | `/berichten` | berichtenCreate | Create a message |
| GET | `/berichten/{uuid}` | berichtenRetrieve | Retrieve a specific message |

**Note**: No PUT, PATCH, or DELETE endpoints. Messages are immutable by design.

## Schemas

### Bericht (Message)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| url | string (uri) | read-only | Unique URL within this API (max 1000) |
| urn | string (urn) | read-only | Uniform Resource Name |
| uuid | string (uuid) | read-only | UUID4 identifier |
| onderwerp | string (max 50) | Yes | Message subject |
| berichtTekst | string (max 4000) | Yes | Message body. URLs rendered as clickable links. Markdown supported for local portals. Newlines only for Mijn Overheid. |
| publicatiedatum | datetime | No (nullable) | When message becomes visible to recipient (default: now) |
| referentie | string (max 25) | No | Sender/internal reference |
| ontvanger | string (urn) | Yes | Recipient URN (BSN or KvK number) |
| geopendOp | datetime | No (nullable) | When recipient opened message in local portal (independent of Mijn Overheid) |
| berichtType | string (max 8) | No | Template code for Mijn Overheid routing. If set, message is forwarded to Mijn Overheid berichtenbox. |
| handelingsPerspectief | string (max 50) | No | Expected action (lezen, naleveren, invullen) |
| einddatumHandelingsTermijn | datetime | No (nullable) | Action deadline |
| bijlagen | array[Bijlage] | No | Attachments |

### Bijlage (Attachment)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| informatieObject | string (urn) | Yes | URN to document (ENKELVOUDIGINFORMATIEOBJECT) |
| omschrijving | string (max 40) | No | Short description/title |
| isBerichtTypeBijlage | boolean | No | If true, attachment is part of Mijn Overheid template (excluded from forwarding) |

### PaginatedBerichtList

| Property | Type | Description |
|----------|------|-------------|
| count | integer | Total results |
| next | string (uri, nullable) | Next page URL |
| previous | string (uri, nullable) | Previous page URL |
| results | array[Bericht] | Messages |

## Key Design Decisions

1. **Immutable messages**: No update or delete endpoints, creating a permanent audit trail
2. **Dual-channel routing**: Messages can appear in both local portal AND Mijn Overheid berichtenbox, controlled by `berichtType`
3. **Markdown for local, plain for national**: `berichtTekst` supports Markdown in local portals but only newlines for Mijn Overheid
4. **Read tracking is portal-only**: `geopendOp` tracks when a message was opened in the local portal, independent of Mijn Overheid's own tracking
5. **Attachment template awareness**: `isBerichtTypeBijlage` controls whether an attachment is forwarded to Mijn Overheid or kept local-only

## Pagination

| Parameter | Type | Default | Max |
|-----------|------|---------|-----|
| page | integer | 1 | - |
| pageSize | integer | 100 | 500 |

## Security

- **OpenID Connect**: `openIdConnect` type
- **Token Authentication**: API key in Authorization header with "Token" prefix
