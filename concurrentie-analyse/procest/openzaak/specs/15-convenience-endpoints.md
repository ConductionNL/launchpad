# Spec: Convenience / Composite Endpoints

## Feature: Single-Call Operations for Common Multi-Step Workflows

OpenZaak provides experimental convenience endpoints that combine multiple API calls into single atomic operations, improving performance and reducing client complexity.

### Already in Procest

- Basic case creation (single API call)
- Basic document creation and linking (separate calls)

### Not Yet in Procest

- **zaak_registreren** — create zaak + status + rollen + zaakinformatieobjecten + zaakobjecten in one call
- **zaak_opschorten** — suspend zaak + set new status in one call
- **zaak_verlengen** — extend zaak + set new status in one call
- **zaak_bijwerken** — update zaak + status + rollen in one call
- **zaak_afsluiten** — close zaak by creating status + resultaat in one call
- **zaaknummer_reserveren** — reserve case numbers (with optional bulk amount)
- **document_registreren** — create document + link to zaak in one call
- **documentnummer_reserveren** — reserve document numbers (with optional bulk amount)
- **besluit_verwerken** — create besluit + link informatieobjecten in one call

## Procest Advantage

As a native Nextcloud app, Procest can implement these composite operations internally without needing external API calls. This is a significant performance advantage over applications that must make multiple network calls to OpenZaak.
