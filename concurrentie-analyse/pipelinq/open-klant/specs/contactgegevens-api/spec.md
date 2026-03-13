# Contactgegevens API -- Open Klant Feature Spec

## Overview

The Contactgegevens API (v1.1.1) is a secondary API in Open Klant. Unlike the Klantinteracties API, it is NOT based on a VNG standard -- it is a custom API unique to Open Klant. It stores personal and organizational contact details separately from the Klantinteracties domain.

## API Endpoints (4 total)

| Resource | Endpoints | Methods |
|---|---|---|
| `/persoon` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/organisatie` | list + detail | GET, POST, PUT, PATCH, DELETE |

## Data Model

### Persoon (Person)
- UUID identifier
- Geslacht (gender): Man, Vrouw, Overig
- Voornamen (first names)
- Voorvoegsel (name prefix)
- Geslachtsnaam (family name)
- Geboortedatum (date of birth) -- required
- Overlijdensdatum (date of death) -- optional
- Address fields (via AdresMixin): straatnaam, huisnummer, huisnummertoevoeging, postcode, stad, adresregel 1-3, land

### Organisatie (Organization)
- UUID identifier
- Handelsnaam (trade name) -- required
- Oprichtingsdatum (founding date)
- Opheffingsdatum (dissolution date)
- Address fields (via AdresMixin): same as Persoon

## Key Observations

1. This API is **separate from the Klantinteracties Partij model** -- it stores more detailed personal/organizational data (birth dates, death dates, gender, founding dates)
2. The Klantinteracties API has its own simpler Partij > Persoon/Organisatie/Contactpersoon hierarchy
3. The two systems are NOT directly linked in the database -- they serve different purposes
4. Contactgegevens is more like a "phonebook" while Klantinteracties tracks interactions

## Comparison with Pipelinq

### Already in Pipelinq
- Person/organization data storage (via OpenRegister)

### Not yet in Pipelinq
- **Separate Contactgegevens API** as a standalone contact registry
- **Geslacht/gender field** with standard choices
- **Life event dates** (geboortedatum, overlijdensdatum)
- **Organization lifecycle dates** (oprichtingsdatum, opheffingsdatum)
- **Standalone address model** separate from klantinteracties
