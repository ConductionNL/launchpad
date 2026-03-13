# Data Model -- Open Klant Feature Spec

## Overview

Open Klant has two separate data domains: Klantinteracties (customer interactions) and Contactgegevens (contact details). They are independent -- no foreign keys between them.

## Entity Relationship Diagram (Klantinteracties)

```
Partij (central entity)
├── soort_partij: persoon | organisatie | contactpersoon
├── 1:1 Persoon (voorletters, voornaam, voorvoegsel, achternaam)
├── 1:1 Organisatie (naam)
├── 1:1 Contactpersoon (voorletters, voornaam, voorvoegsel, achternaam, werkte_voor_partij -> Partij[organisatie])
├── 1:N DigitaalAdres (email, telefoon, overig)
├── 1:N Rekeningnummer
├── 1:N PartijIdentificator (BSN, KVK, RSIN, Vestigingsnummer)
├── N:M Klantcontact (via Betrokkene join table)
├── N:M Partij (via Vertegenwoordigden -- representation)
├── N:M Categorie (via CategorieRelatie)
├── FK voorkeurs_digitaal_adres -> DigitaalAdres
└── FK voorkeurs_rekeningnummer -> Rekeningnummer

Klantcontact
├── 1:N Betrokkene (links to Partij with role + contactnaam + addresses)
├── 1:N ActorKlantcontact (links to Actor)
├── 1:N Onderwerpobject (external object references)
├── 1:N Bijlage (attachment references)
└── 1:N InterneTaak (assigned to Actor)

Actor
├── soort_actor: medewerker | geautomatiseerde_actor | organisatorische_eenheid
├── 1:1 Medewerker
├── 1:1 GeautomatiseerdeActor
├── 1:1 OrganisatorischeEenheid
├── actoridentificator (code_objecttype, code_soort_object_id, object_id, code_register)
└── N:M Klantcontact (via ActorKlantcontact)
```

## Mixins

### BezoekadresMixin
Fields: nummeraanduiding_id, straatnaam, huisnummer (int), huisnummertoevoeging, postcode ("1234 AB"), stad, adresregel1-3, land (ISO 3166)
Used by: Partij, Betrokkene

### CorrespondentieadresMixin
Same fields as BezoekadresMixin but prefixed differently.
Used by: Partij, Betrokkene

### ContactnaamMixin
Fields: voorletters, voornaam, voorvoegsel_achternaam, achternaam
Method: get_full_name()
Used by: Persoon, Contactpersoon, Betrokkene

### AdresMixin (Contactgegevens)
Fields: straatnaam, huisnummer, huisnummertoevoeging, postcode, stad, adresregel1-3, land
Used by: Contactgegevens.Persoon, Contactgegevens.Organisatie

## Constants / Enumerations

| Enum | Values |
|---|---|
| SoortPartij | persoon, organisatie, contactpersoon |
| SoortActor | medewerker, geautomatiseerde_actor, organisatorische_eenheid |
| Klantcontrol (Rol) | vertegenwoordiger, klant |
| Taakstatus | te_verwerken, verwerkt |
| PartijIdentificatorCodeObjectType | natuurlijk_persoon, niet_natuurlijk_persoon, vestiging |
| PartijIdentificatorCodeSoortObjectId | bsn, vestigingsnummer, kvk_nummer, rsin |
| PartijIdentificatorCodeRegister | brp, hr |
| GeslachtChoices | Man, Vrouw, Overig |

## Key Design Patterns

1. **Polymorphic party model**: Partij acts as base; Persoon/Organisatie/Contactpersoon are 1:1 subtypes
2. **GegevensGroepType**: Django descriptor that groups related fields into a single dict in API responses (used for addresses, identificators)
3. **Join table with payload**: Betrokkene links Partij to Klantcontact with additional data (role, contact info, addresses)
4. **Self-referential FK**: Vertegenwoordigden links Partij to Partij; PartijIdentificator.sub_identificator_van links to parent PartijIdentificator
5. **UUID-based identification**: All entities use UUID as primary identifier in API, integer PK internally
6. **Nummer fields deprecated**: Both Partij.nummer and Klantcontact.nummer are marked deprecated but still required as integers

## Uniqueness Constraints

- Partij.nummer: unique
- Klantcontact.nummer: unique
- Klantcontact.referentienummer: unique
- PartijIdentificator: composite unique on (objecttype, soort_object_id, object_id, register) globally, plus (partij, soort_object_id) locally
- Vertegenwoordigden: unique_together on (vertegenwoordigende_partij, vertegenwoordigde_partij)

## Comparison with Pipelinq

### Already in Pipelinq
- OpenRegister provides flexible object/schema model for any entity type
- UUID-based identification

### Not yet in Pipelinq
- **VNG-compliant data model** for klantinteracties
- **Polymorphic party system** with typed subtypes
- **GegevensGroepType** pattern for nested field groups
- **BRP/HR register linking** via PartijIdentificator
- **Representation model** (Vertegenwoordigden)
- **Hierarchical identifiers** (sub_identificator_van)
- **Contact-to-object linking** (Onderwerpobjecten referencing external zaken)
- **Attachment reference model** (Bijlagen)
- **Task assignment model** (InterneTaak -> Actor)
