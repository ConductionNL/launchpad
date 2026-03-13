---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Business Search (KvK Integration) - KISS

## Purpose
Search for Dutch businesses via the KvK (Kamer van Koophandel / Chamber of Commerce) API. KCMs use this to identify and link businesses to contactmomenten, similar to person search but for organizational entities.

## Architecture Overview
- **Frontend**: BedrijfZoeker.vue form, BedrijvenOverzicht.vue results table
- **BFF**: KvkProxyConfig reverse proxy with API key and optional custom headers
- **External**: KvK Zoeken API

## Data Model

### Bedrijf (Frontend)
```typescript
interface Bedrijf {
  _typeOfKlant: "bedrijf";
  kvkNummer: string;
  type: string;                      // hoofdvestiging, nevenvestiging, etc.
  vestigingsnummer?: string;
  rsin?: string;
  bedrijfsnaam: string;
  postcode?: string;
  huisnummer?: string;
  straatnaam: string;
  huisletter?: string;
  huisnummertoevoeging?: string;
  woonplaats?: string;
}
```

### Search Options
```typescript
type BedrijfSearchOptions =
  | { handelsnaam: string }          // Trade name search
  | { kvkNummer: string }            // KvK number exact
  | { postcodeHuisnummer: PostcodeHuisnummer }
  | { vestigingsnummer: string };    // Branch number exact
```

### BedrijfIdentifier (for klant linking)
```typescript
type BedrijfIdentifier =
  | { vestigingsnummer: string; kvkNummer: string }
  | { kvkNummer: string }
  | { vestigingsnummer: string };
```

## Business Logic
1. KCM searches by trade name, KvK number, postcode+housenumber, or vestigingsnummer
2. BFF proxies to KvK API with API key authentication
3. Results shown in overview table with pagination
4. KCM selects business, system creates/finds matching "Partij" in OpenKlant
5. Complex identifier hierarchy: KvK number -> vestiging -> partij-identificator with `subIdentificatorVan` linking

### OpenKlant 2 Integration
The system manages a hierarchy of identifiers:
- A "niet-natuurlijk persoon" (legal entity) has a KvK nummer
- A "vestiging" (branch) has a vestigingsnummer linked via `subIdentificatorVan` to the KvK identifier
- Both map to a single "Partij" (party) in OpenKlant

## Requirements (as observed)
- Must support trade name fuzzy search
- Must support exact KvK number lookup
- Must support postcode + housenumber search
- Must support vestigingsnummer lookup
- Must handle KvK/vestiging hierarchy correctly in OpenKlant

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| KvK integration | Yes (native) | No |
| Business registry search | Yes | No |
| Organization management | Via external KvK data | Internal org records |
| Duplicate detection | Via KvK/vestigingsnr matching | Yes (built-in) |
| Org hierarchy | KvK -> vestiging model | Flat organizations |
| Data enrichment | From KvK registry | Manual entry |

**Gap for Pipelinq**: KvK API integration would be valuable for Dutch government users. Could be implemented as an n8n integration or direct API connector.
