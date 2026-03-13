# Spec: Zaaktype Configuration Model

## Feature: Complete Zaaktype Configuration with All Related Types

The zaaktype configuration model defines how cases are processed — their lifecycle, participants, documents, decisions, properties, and archiving rules.

### Already in Procest

- Zaaktype CRUD (CaseTypeAdmin.vue, CaseTypeDetail.vue)
- Zaaktype listing (CaseTypeList.vue)
- Basic zaaktype attributes (omschrijving, doorlooptijd)
- Statustype definitions per zaaktype
- Resultaattype definitions
- Roltype definitions
- Eigenschap definitions
- InformatieObjecttype definitions
- Besluittype definitions

### Not Yet in Procest

- **Catalogus grouping** — organizing zaaktypen under catalogs
- **Concept/publish workflow** — draft vs. published zaaktypen
- **Zaaktype versioning** — creating new versions with validity periods (beginGeldigheid/eindeGeldigheid)
- **Publication dependency enforcement** — requiring all related types to be published before zaaktype (ztc-012)
- **Immutability after publication** — preventing changes to published types (ztc-009)
- **Servicenorm** — service level agreement duration
- **Selectielijstprocestype** — linking to national archiving process type
- **ZaaktypeInformatieobjecttype** — formal associations with volgnummer and richting (inkomend/uitgaand/intern)
- **ZaakObjectType** — defining what objects a case can relate to
- **Cross-catalog restriction enforcement**
- **Catalog export/import** — .zip archive with all type definitions
- **Zaaktype export/import** — individual zaaktype with related types
- **Correction/amendment model** — making corrections to published types under strict rules
- **Vertrouwelijkheidaanduiding default** per zaaktype
- **Trefwoorden** (keywords) per zaaktype
- **Producten/diensten** — linking zaaktypen to government products
- **Referentieproces** — linking to GEMMA reference processes
