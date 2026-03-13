# VNG ZGW Standard: Documenten API

## Overview

The Documenten API facilitates storage and disclosure of documents (informatieobjecten) and associated metadata. An "informatieobject" is a generalized concept covering text files, photographs, audio, datasets, web pages, maps, etc.

**Current version:** 1.5.0 (14-03-2024)
**Concept version:** 1.6.0

## Data Model

### EnkelvoudigInformatieObject (Document)
Core resource representing a single document. Key attributes:
- `identificatie` — human-readable identifier
- `bronorganisatie` — source organization (RSIN)
- `creatiedatum` — creation date
- `titel` — title
- `auteur` — author
- `taal` — language (ISO 639-2/B)
- `inhoud` — file content (base64 encoded)
- `bestandsnaam` — filename
- `bestandsomvang` — file size in bytes
- `formaat` — MIME type
- `status` — in_bewerking, ter_vaststelling, definitief, gearchiveerd
- `vertrouwelijkheidaanduiding` — confidentiality level
- `informatieobjecttype` — link to type definition
- `ontvangstdatum` — date received (for incoming documents)
- `verzenddatum` — date sent (for outgoing documents)
- `indicatieGebruiksrechten` — usage rights indicator
- `ondertekening` — digital signature info
- `integriteit` — integrity check info
- `verschijningsvorm` — form of appearance
- `trefwoorden` — keywords

### Version History
Documents maintain a version history. Each update creates a new version while preserving previous versions.

### ObjectInformatieObject
Links documents to objects (zaken, besluiten). The ZaakInformatieObject (in ZRC) takes precedence over ObjectInformatieObject (in DRC).

### Gebruiksrecht (Usage Rights)
Defines usage conditions for documents.

### Verzending (Shipment) — v1.2.0+
Records document transmissions to/from contacts. Address types include:
- Domestic/international correspondence address
- Postal address, fax, email
- mijnOverheid portal, phone number

## Document Lifecycle

### Status Transitions
- When `ontvangstdatum` has a value: statuses "in bewerking" and "ter vaststelling" are **prohibited**
- Status "definitief" means the document is finalized
- Status "gearchiveerd" means the document is archived

### Locking Mechanism (drc-009)
1. Consumer executes lock operation
2. DRC responds with non-guessable `lockId`
3. All write operations blocked without correct `lockId`
4. Administrators with `documenten.geforceerd-unlock` scope can force unlock

### Update Constraints (drc-010)
Updates validated for:
- Correct lock value
- Non-final status (v1.4.0+)
- Informatieobjecttype immutability (cannot change type after creation)

### Deletion Rules (drc-008)
- Can only delete when **no** ObjectInformatieObject relationships remain
- Actual deletion mandatory (soft-deletes prohibited)
- Related usage rights and audit trails must be removed

## File Upload

### Standard Upload (v1.0.x)
Files base64-encoded in the `inhoud` attribute. Maximum ~3GB (accounting for base64 overhead of ~33%).

### Chunked Upload (v1.1.0+)
For files exceeding 3GB:
1. Create informatieobject with total file size
2. API returns file chunk specifications
3. Upload each chunk with lock ID
4. Unlock; provider validates and assembles chunks
5. Web servers must support minimum 4.0 GiB request bodies

## Confidentiality (drc-007)

When clients provide a `vertrouwelijkheidaanduiding`, the provider must use it. Without explicit value, it derives from the informatieobjecttype's default. Responses must always contain a valid confidentiality value.

## Usage Rights (drc-006)

- No conditions: `indicatieGebruiksrechten = false`
- Unknown: `indicatieGebruiksrechten = null`
- Has conditions: `indicatieGebruiksrechten = true` (requires creating Gebruiksrecht resource)
- Provider auto-updates indicator on Gebruiksrecht create/delete

## Bulk Import (Open Zaak Extension)

Open Zaak provides non-standard bulk import endpoints:
1. `POST /import/create` — create import job
2. `POST /import/{uuid}/upload` — upload CSV metadata file
3. `GET /import/{uuid}/status` — check progress
4. `GET /import/{uuid}/report` — download result report
5. `DELETE /import/{uuid}/delete` — clean up

Requirements:
- Application must have `heeft_alle_autorisaties = True`
- Only one active import at a time
- CSV file with document metadata + `bestandspad` (relative file path)
- Processes in batches (configurable via `IMPORT_DOCUMENTEN_BATCH_SIZE`)
- **No notifications** sent during import

## Document Storage Backends

Open Zaak supports three storage backends:
1. **Filesystem** (default) — local filesystem storage
2. **Azure Blob Storage** — Azure cloud storage
3. **S3 Storage** — S3-compatible storage (AWS, MinIO, etc.)

Switching backends does **not** migrate existing files.

## HTTP Caching

ETag headers on JSON representations. HEAD requests return headers without body. If-None-Match for conditional requests.

## Archive Formation

All informatieobjecten of a zaak constitute the **zaakarchief**. Combined with case characteristics, they form the **zaakdossier**.
