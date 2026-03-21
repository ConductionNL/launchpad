---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Document Management -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements document (informatieobject) management.
- **Product**: Dimpact ZAC
- **Category**: Document Management
- **Relevance to Procest**: Document handling is essential for case processing -- upload, versioning, signing, sending

## Architecture Overview
Documents are stored in the ZGW DRC (Documenten Registratie Component). ZAC provides upload, versioning, locking, signing, sending, converting, and viewing. WebDAV integration enables in-browser editing.

Key services:
- `EnkelvoudigInformatieObjectRestService` -- REST endpoint at `/rest/informatieobjecten`
- `EnkelvoudigInformatieObjectUpdateService` -- update operations
- `EnkelvoudigInformatieObjectConvertService` -- PDF conversion
- `EnkelvoudigInformatieObjectDownloadService` -- download/zip
- `EnkelvoudigInformatieObjectLockService` -- local lock tracking

## Data Model

### Document Properties (from DRC)
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Document identifier |
| identificatie | String | Human-readable ID |
| titel | String | Title |
| beschrijving | String | Description |
| bestandsnaam | String | File name |
| formaat | String | MIME type |
| status | StatusEnum | CONCEPT, DEFINITIEF, GEARCHIVEERD |
| versie | Integer | Version number |
| vertrouwelijkheidaanduiding | Enum | Confidentiality level (7 levels) |
| ontvangstdatum | LocalDate | Received date |
| verzenddatum | LocalDate | Sent date |
| locked | Boolean | Lock state |
| ondertekening | Ondertekening | Signature info |

### Confidentiality Levels
OPENBAAR, BEPERKT_OPENBAAR, INTERN, ZAAKVERTROUWELIJK, VERTROUWELIJK, CONFIDENTIEEL, GEHEIM, ZEER_GEHEIM

### Document Permissions (from OPA)
lezen, wijzigen, verwijderen, vergrendelen, ontgrendelen, ondertekenen, toevoegen_nieuwe_versie, verplaatsen, ontkoppelen, downloaden, converteren

## Business Logic

### Document Upload
1. Multipart form upload with metadata
2. Check `zaak.toevoegenDocument` policy
3. Create EnkelvoudigInformatieObject in DRC
4. Create ZaakInformatieobject link in ZRC
5. Return REST representation

### Document Versioning
- New versions created via update endpoint
- Check `document.toevoegenNieuweVersie` policy
- Supports viewing specific versions
- Each version has its own content

### Document Locking
- Lock: creates local `EnkelvoudigInformatieObjectLock` record + locks in DRC
- Only lock owner or recordmanager can unlock
- Lock state affects edit/delete/sign permissions via OPA policy

### Document Signing (Ondertekenen)
- Check not already signed + `document.ondertekenen` policy
- Sets ondertekening metadata on document in DRC

### Document Sending (Verzenden)
Prerequisites: status=DEFINITIEF, not confidential, PDF format, no ontvangstdatum
- Sets verzenddatum on document

### Document Conversion (to PDF)
- Requires `document.converteren` policy + document must be DEFINITIEF
- Delegates to external Office Converter service
- Creates new version with PDF content

### Document Moving (Verplaatsen)
- Move between cases or from inbox/decoupled documents
- Updates ZaakInformatieobject references
- Tracks provenance in toelichting

### Document Download
- Single download with content-disposition
- Zip download of multiple documents
- Version-specific downloads

### WebDAV Editing
- `GET /rest/informatieobjecten/informatieobject/{uuid}/edit` returns WebDAV redirect URI
- Enables in-browser editing of Office documents

### Inbox Documents
- Documents received but not yet linked to a case
- Can be moved to a case or deleted

### Decoupled Documents
- Documents unlinked from their original case
- Managed by recordmanager
- Can be re-linked or deleted

## Requirements (as observed)

1. Documents are stored externally in ZGW DRC -- ZAC only manages locks locally
2. Locking prevents concurrent editing with a local tracking table
3. Confidentiality affects sending permissions (CONFIDENTIEEL+ cannot be sent)
4. Only PDF documents in DEFINITIEF status can be sent
5. Signing is a one-time operation -- cannot sign already-signed documents
6. Document permissions depend on: role, zaak state, document state, lock state, zaaktype
7. WebDAV support requires external Office suite integration

## User-Facing Features (from User Manual)

### Document Operations Available to Users
- Upload and attach documents to cases
- Edit MS Office formats (Word, Excel, PowerPoint) -- creates versioned copies via WebDAV
- Modify metadata independently of content
- Digital signing with timestamp recording
- Convert Office documents to PDF (via OfficeConverter service)
- Transmit documents with date/status tracking
- Move documents between cases
- Detach documents (moved to separate "ontkoppelde documenten" worklist)
- Delete documents (Recordmanager role only)
- Preview PDFs and images inline

### Document Status Workflow
- Draft -> Final -> Definitive progression
- Status affects available operations (e.g., cannot edit Definitive documents except as Recordmanager)

### SmartDocuments Integration
- Document creation wizard with pre-filled case/task data
- Template-to-document-type mapping per zaaktype
- Callback-based flow: SmartDocuments creates document -> calls ZAC -> ZAC stores in Open Zaak
- Documents auto-linked to originating case/task

## Comparison Notes
- ZAC relies entirely on DRC for storage; Procest stores documents in Nextcloud file system
- The locking mechanism with local tracking is necessary due to ZGW API limitations
- WebDAV integration for in-browser editing is a notable feature -- enables MS Office collaboration
- The confidentiality level system with 8 tiers is comprehensive
- Document conversion via external service (OfficeConverter/LibreOffice) adds infrastructure complexity
- SmartDocuments integration for template-based document creation is a differentiating enterprise feature
- Document preview is limited to PDF/images -- no Office document preview
