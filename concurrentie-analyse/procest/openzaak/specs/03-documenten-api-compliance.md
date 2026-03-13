# Spec: Documenten API Compliance

## Feature: Full VNG Documenten API Implementation

OpenZaak implements the complete Documenten API (v1.4.2) for document storage, retrieval, versioning, locking, and lifecycle management.

### Already in Procest

- Document (EnkelvoudigInformatieObject) creation and listing
- Document upload with base64 encoding
- Linking documents to cases (ZaakInformatieObject)
- Document metadata: titel, auteur, creatiedatum, taal, bestandsnaam
- Confidentiality levels on documents
- InformatieObjecttype association
- ZGW document business rules: ZgwDrcRulesService.php
- ZgwDocumentService.php for document operations
- Nextcloud native file integration (unique to Procest)

### Not Yet in Procest

- Document version history (maintaining previous versions on update)
- Document locking/unlocking mechanism (lockId-based concurrency control)
- Forced unlock capability (documenten.geforceerd-unlock scope)
- Status lifecycle enforcement (in_bewerking -> ter_vaststelling -> definitief -> gearchiveerd)
- Status restrictions with ontvangstdatum (incoming docs cannot be "in bewerking")
- Gebruiksrecht (usage rights) management
- indicatieGebruiksrechten tracking
- Verzending (shipment) tracking — address types, transmission records
- Chunked upload for large files (>3GB, bestandsdelen)
- Bulk import via CSV metadata + filesystem files
- ObjectInformatieObject relationships (linking docs to non-zaak objects)
- Deletion rules enforcement (only when no relationships remain)
- Ondertekening (digital signature) metadata
- Integriteit (integrity check) metadata
- Verschijningsvorm (form of appearance) tracking
- Trefwoorden (keywords) support
- ETag/HTTP caching on document resources
- Expand parameter support
- Document storage backend abstraction (Azure Blob, S3)
