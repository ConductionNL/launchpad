# Spec: Document Lifecycle Management

## Feature: Complete Document Lifecycle with Locking, Versioning, and Status

OpenZaak implements a sophisticated document lifecycle with concurrent editing protection, version history, and status-based workflow.

### Already in Procest

- Document creation and upload
- Document metadata management
- Document linking to cases
- Basic document listing and detail views
- Nextcloud file system integration (native advantage)

### Not Yet in Procest

- **Locking mechanism** — lock acquisition before updates, lockId-based concurrency control
- **Forced unlock** — admin capability to break locks (documenten.geforceerd-unlock)
- **Version history** — maintaining all previous versions of a document
- **Status workflow:**
  - `in_bewerking` (in progress)
  - `ter_vaststelling` (pending approval)
  - `definitief` (finalized)
  - `gearchiveerd` (archived)
- **Status restrictions:**
  - Incoming documents (with ontvangstdatum) cannot have status "in_bewerking" or "ter_vaststelling"
  - Updates blocked on definitief documents (v1.4.0+)
- **Gebruiksrecht** (usage rights) — conditions under which a document may be used
- **Verzending** (shipment) — tracking to/from which address a document was sent/received
- **Ondertekening** (digital signature) metadata
- **Integriteit** (integrity check) metadata
- **Chunked upload** for files >3GB
- **Bulk import** via CSV + filesystem
- **Storage backend abstraction** — filesystem, Azure Blob, S3
- **Deletion enforcement** — only when no relationships remain
