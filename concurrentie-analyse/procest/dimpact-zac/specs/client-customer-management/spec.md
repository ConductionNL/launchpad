---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Client/Customer (Klant) Management -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC manages citizens and businesses as case participants.
- **Product**: Dimpact ZAC
- **Category**: Customer Management
- **Relevance to Procest**: Government cases involve citizens and businesses as initiators and involved parties

## Architecture Overview
ZAC integrates with external registries for customer data. Citizens come from BRP (via BSN), businesses from KVK (via KVK number/vestigingsnummer). Contact details come from the Klanten API. ZAC does not store customer data locally.

Key service: `KlantRestService` at `/rest/klanten`

## Data Model

### IdentificatieType
- `BSN` -- Citizen identification (Burgerservicenummer)
- `VN` -- Branch identification (Vestigingsnummer, format: KVK:VN)
- `RSIN` -- Legal entity identification

### RestPersoon (Person/Citizen)
| Field | Type | Description |
|-------|------|-------------|
| bsn | String | BSN number |
| naam | String | Full name |
| geboortedatum | LocalDate | Birth date |
| geslacht | String | Gender |
| verblijfplaats | Address | Residential address |
| indicaties | RestPersoonIndicaties | Deceased, confidential, etc. |

### RestBedrijf (Business)
| Field | Type | Description |
|-------|------|-------------|
| kvkNummer | String | KVK number |
| vestigingsnummer | String | Branch number |
| naam | String | Company name |
| adres | RestKlantenAdres | Business address |
| type | String | Business type |

### RestKlant (Customer -- unified)
| Field | Type | Description |
|-------|------|-------------|
| identificatieType | IdentificatieType | BSN, VN, or RSIN |
| identificatie | String | ID value |
| naam | String | Display name |
| contactDetails | ContactDetails | Email, phone |

### ContactDetails
| Field | Type | Description |
|-------|------|-------------|
| emailadres | String | Email address |
| telefoonnummer | String | Phone number |

### Betrokkene (Involved Party on a Case)
Roles for betrokkenen on a case:
- INITIATOR -- case requester
- ADVISEUR -- advisor
- BELANGHEBBENDE -- interested party
- BESLISSER -- decision maker
- KLANTCONTACTER -- contact person
- MEDE_INITIATOR -- co-initiator
- ZAAKCOORDINATOR -- case coordinator

## Business Logic

### Person Search
- Search by BSN, name, date of birth, address
- Results from BRP (Basisregistratie Personen)
- Privacy: BRP queries require doelbinding (purpose binding) configuration
- BRP verwerkingsregister: logged processing records

### Business Search
- Search by KVK number, vestigingsnummer, name
- Results from KVK (Kamer van Koophandel)

### Contact Details
- Retrieved from Klanten API or Contactgegevens API
- Separate from BRP/KVK data
- Used for communication (email, phone)

### Adding Betrokkenen to Case
1. Search person or business
2. Select role type from configured betrokkene roles
3. Check for duplicates (same identification + role type = error)
4. Create Rol record in ZRC
5. For initiator: specific role type `INITIATOR`

### Contact Moments
- Lists contact moments for a customer
- Linked to cases via the Contactmomenten API

## Requirements (as observed)

1. Customer data is NEVER stored locally -- always retrieved from registries
2. BRP access requires purpose binding (doelbinding) for privacy compliance
3. KVK integration supports both company and branch level
4. Betrokkene deduplication prevents duplicate role assignments
5. Contact details are managed separately from registry data
6. Multiple betrokkene roles supported per case

## Comparison Notes
- ZAC's registry-first approach avoids data duplication but adds API dependency
- The BRP doelbinding requirement is specific to Dutch privacy law
- Procest could support BSN/KVK lookup through similar integrations
- The betrokkene role system maps directly to ZGW standards
