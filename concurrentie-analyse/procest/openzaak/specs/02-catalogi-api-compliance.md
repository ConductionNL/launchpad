# Spec: Catalogi API Compliance

## Feature: Full VNG Catalogi API Implementation

OpenZaak implements the complete Catalogi API (v1.3.1) for managing zaaktype catalogs including all type definitions, versioning, publishing model, and archiving configuration.

### Already in Procest

- Zaaktype configuration admin (CaseTypeAdmin.vue, CaseTypeDetail.vue, CaseTypeList.vue)
- Zaaktype creation and editing
- Statustype management (defining status flow per zaaktype)
- Resultaattype management (defining possible outcomes)
- Roltype management (defining participant roles)
- Eigenschap (property) definitions per zaaktype
- InformatieObjecttype management (document type definitions)
- Besluittype management (decision type definitions)
- ZGW catalog business rules: ZgwZtcRulesService.php
- Settings for catalog API endpoint configuration

### Not Yet in Procest

- Full Catalogus container model (grouping types under catalogs)
- Cross-catalog restriction enforcement (ztc-013)
- Concept/publishing model (concept=true/false, publish action)
- Immutability rules after publication (ztc-009, ztc-010, ztc-011)
- Zaaktype versioning (new versions with beginGeldigheid/eindeGeldigheid)
- Catalog export/import (.zip archives)
- Zaaktype export/import with selective type mapping
- Selectielijst integration for archiving (selectielijstklasse, procestermijn, afleidingswijze)
- brondatumArchiefprocedure full configuration per resultaattype
- ZaaktypeInformatieobjecttype associations (volgnummer, richting)
- ZaakObjectType definitions
- Correction/amendment model for published types
- Read authorization handling (ztc-014 — auto-granting catalogi.lezen for ZRC/DRC consumers)
- ETag/HTTP caching on catalog resources
- Full validation chain: ztc-001 through ztc-012
