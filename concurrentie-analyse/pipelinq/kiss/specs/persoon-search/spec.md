---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Person Search (BRP Integration) - KISS

## Purpose
Search for Dutch citizens via the Haal Centraal BRP (Basisregistratie Personen) API. This is critical for government customer service - the KCM needs to identify the citizen they're speaking with and pull up their registered personal data.

## Architecture Overview
- **Frontend**: PersoonZoeker.vue form with BSN, name+DOB, postcode+housenumber search modes
- **BFF**: HaalCentraalProxyConfig reverse proxy with API key injection
- **External**: Haal Centraal BRP Personen Bevragen API

## Data Model

### Persoon (Frontend)
```typescript
interface Persoon {
  _typeOfKlant: "persoon";
  bsn: string;
  geboortedatum?: Date;
  geslacht: string;
  voornaam: string;
  voorvoegselAchternaam?: string;
  achternaam: string;
  geboorteplaats?: string;
  geboorteland?: string;
  adresregel1?: string;
  adresregel2?: string;
  adresregel3?: string;
  geheimhoudingPersoonsgegevens?: boolean;
}
```

### Search Modes
```typescript
type PersoonQuery =
  | { bsn: string }
  | { postcodeHuisnummerAchternaam: PostcodeHuisnummerMetAchternaam }
  | { geslachtsnaamGeboortedatum: GeslachtsnaamGeboortedatum };
```

## Business Logic
1. KCM enters search criteria (BSN, or postcode+housenumber+name, or surname+DOB)
2. BFF proxies request to Haal Centraal BRP API with API key authentication
3. Results displayed in table, KCM selects person
4. Selected person is linked to current contactmoment as "klant"
5. System checks if person already exists as "Partij" in OpenKlant, creates if not

### Privacy
- BRP data includes `geheimhoudingPersoonsgegevens` flag for restricted records
- All BRP lookups are logged in verwerking (processing) log for AVG compliance

## Requirements (as observed)
- Must support BSN lookup
- Must support postcode + housenumber + surname search
- Must support surname + date of birth search
- Must display privacy restriction indicator
- Must log all lookups for AVG compliance

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| BRP integration | Yes (native) | No |
| BSN lookup | Yes | No |
| Government ID search | Yes | No |
| Contact search | BRP-based | Internal contacts DB |
| Privacy flags | Yes | No |
| Audit logging | Yes (verwerking) | No |
| Data source | External registry | Own database |

**Gap for Pipelinq**: BRP integration is government-specific and requires API access agreements. Not relevant for non-government use cases, but critical for municipalities.
