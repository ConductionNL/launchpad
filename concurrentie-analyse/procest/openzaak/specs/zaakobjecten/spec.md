---
title: ZaakObjecten (Case Objects)
component: Zaken API (ZRC)
priority: medium
---

# ZaakObjecten (Case Objects)

## Purpose

ZaakObjecten link cases to external objects from various registrations (BAG, BRP, Kadaster, etc.). OpenZaak supports 20+ RGBZ object types with dedicated models, plus a generic "Overige" type for custom objects.

### Relevance to Procest

Dutch government cases often relate to physical objects (buildings, addresses), people, organisations, and other registered entities. This integration with base registries is important.

## Data Model - ZaakObject (Base)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID4 | auto | Unique identifier |
| zaak | FK(Zaak) | yes | Case reference |
| object | URLField(1000) | no | External object URL |
| object_type | choices | yes | Object type from ZaakobjectTypes enum |
| object_type_overige | CharField(100) | conditional | Custom type when object_type=overige |
| object_type_overige_definitie | GegevensGroep | no | Custom type definition (url, schema, objectData) |
| relatieomschrijving | CharField(80) | no | Relationship description |

### Supported Object Types (dedicated models)

| Type | Model | Key Fields |
|------|-------|------------|
| adres | Adres | wpl_woonplaats_naam, gor_openbare_ruimte_naam, huisnummer, postcode |
| buurt | Buurt | buurt_code, buurt_naam, gem_gemeente_code |
| gemeente | Gemeente | gemeente_naam, gemeente_code |
| gemeentelijke_openbare_ruimte | GemeentelijkeOpenbareRuimte | identificatie, openbare_ruimte_naam |
| huishouden | Huishouden | nummer |
| inrichtingselement | Inrichtingselement | type, identificatie, naam |
| kunstwerkdeel | Kunstwerkdeel | type, identificatie, naam |
| maatschappelijke_activiteit | MaatschappelijkeActiviteit | kvk_nummer, handelsnaam |
| openbare_ruimte | OpenbareRuimte | identificatie, wpl_woonplaats_naam |
| pand | Pand | identificatie |
| spoorbaandeel | Spoorbaandeel | type, identificatie |
| terreindeel | Terreindeel | type, identificatie |
| waterdeel | Waterdeel | type_waterdeel, identificatie |
| wegdeel | Wegdeel | type, identificatie |
| wijk | Wijk | wijk_code, wijk_naam |
| woonplaats | Woonplaats | identificatie, woonplaats_naam |
| terrein_gebouwd_object | TerreinGebouwdObject | identificatie |
| woz_deelobject | WozDeelobject | nummer_woz_deel_object |
| woz_waarde | WozWaarde | waardepeildatum |
| woz_object | WozObject | woz_object_nummer |
| zakelijk_recht | ZakelijkRecht | identificatie, avg_aard |
| kadastrale_onroerende_zaak | KadastraleOnroerendeZaak | kadastrale_identificatie |
| overige | Overige | overige_data (JSONField) |

## API Endpoints

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| GET/POST | /zaken/v1/zaakobjecten | zaken.lezen/aanmaken | CRUD case objects |
| GET | /zaken/v1/zaakobjecten/{uuid} | zaken.lezen | Retrieve case object |

## Procest Comparison

| Feature | Already in Procest | Not yet in Procest |
|---------|-------------------|-------------------|
| External object references | No | URL references to base registries |
| 20+ RGBZ object type models | No | Dedicated models per type |
| Generic overige type | No | JSON-based custom objects |
| BAG integration (pand, adres) | No | Adres, Pand models |
| WOZ integration | No | WozObject, WozWaarde models |
| Kadaster integration | No | KadastraleOnroerendeZaak, ZakelijkRecht |
| KvK integration | No | MaatschappelijkeActiviteit |
