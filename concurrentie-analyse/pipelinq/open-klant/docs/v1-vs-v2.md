# Open Klant v1 vs v2 -- API Evolution

## Version 1.0.0 (Legacy -- support until March 2026)

### APIs Implemented
1. **Klanten API** (VNG standard) -- Customer data storage
2. **Contactmomenten API** (VNG standard) -- Contact moment logging

### Key Concepts (v1)
- **Klant**: A natural person, optionally representing a legal entity
- **Contactmoment**: A continuous interaction period between parties
- **Objectcontactmoment**: Links contact to external objects
- **Klantcontactmoment**: Links contact to customer with role
- **Verzoek**: A request for municipal product/service

### Data Model (v1)
- Simple flat model per entity
- Customer linked to contact via `klantcontactmoment` join
- Sequential linking via `vorigContactmoment` / `volgendContactmoment`
- ETag-based HTTP caching required

---

## Version 2.0.0+ (Current -- Klantinteracties API)

### APIs Implemented
1. **Klantinteracties API** (v0.6.0) -- Based on VNG semantic model (NOT a formal standard)
2. **Contactgegevens API** (v1.1.1) -- Custom API for basic contact data

### Key Concepts (v2)
- **Partij**: Replaces Klant. Polymorphic: Persoon/Organisatie/Contactpersoon
- **Klantcontact**: Replaces Contactmoment. Richer metadata (JSON, confidentiality, success indicator)
- **Betrokkene**: New. Links Partij to Klantcontact with role and point-in-time data
- **Onderwerpobject**: Replaces Objectcontactmoment. Generic external object reference
- **Bijlage**: New. Document references
- **Actor**: New. Municipal side of interaction
- **InterneTaak**: New. Follow-up tasks from contacts
- **DigitaalAdres**: New. Multi-channel contact addresses with defaults
- **Rekeningnummer**: New. Bank account tracking
- **Categorie/CategorieRelatie**: New. Party categorization (experimental)
- **Vertegenwoordigden**: New. Party representation

### Key Differences

| Aspect | v1 | v2 |
|--------|-----|-----|
| Customer model | Flat Klant | Polymorphic Partij (3 subtypes) |
| Contact model | Simple Contactmoment | Rich Klantcontact + Betrokkene |
| Actor tracking | None | Full Actor model (3 subtypes) |
| Task management | None | InterneTaak |
| Digital addresses | Inline fields | Separate DigitaalAdres entity |
| Bank accounts | None | Rekeningnummer |
| Categories | None | Categorie + CategorieRelatie |
| Representation | None | Vertegenwoordigden |
| VNG API identifiers | BSN inline | PartijIdentificator (BRP/HR) |
| Sequential contacts | vorigContactmoment/volgendContactmoment | via Onderwerpobject.was_klantcontact |
| HTTP caching | ETag required | Not required |
| Auth | Same token-based | Same token-based |
| Composite endpoint | None | maak-klantcontact |
| Cloud events | None | zaak-gekoppeld/zaak-ontkoppeld (experimental) |
| Observability | None | OpenTelemetry + structlog |

### Migration Path

`migrate_to_v2` management command migrates Klant instances from v1 to v2:
- Creates Partij records from Klant records
- Maps identifiers to PartijIdentificator
- Additional `migrate_to_v2_phonenumbers` for phone number migration
- Handles 8-digit BSNs with leading zero padding
