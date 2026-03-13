# Spec: Case Closure and Automatic Archiving

## Feature: VNG-Compliant Case Closure with Automatic Archive Date Calculation

When a case is closed (final status set), the system must automatically derive archiving parameters from the resultaat configuration.

### Already in Procest

- Status management on cases
- Result (resultaat) setting on cases
- Basic case lifecycle awareness

### Not Yet in Procest

- **Case closure enforcement:**
  - Require a Resultaat before allowing final status
  - Final status = highest-numbered StatusType in the ZaakType
  - Setting final status = closing the case
- **Automatic archive parameter derivation on closure:**
  - Read the ResultaatType's selectielijstklasse
  - Determine the afleidingswijze (derivation method)
  - Calculate the brondatum (archive start date) based on the method
  - Calculate archiefactiedatum = brondatum + archiefactietermijn
  - Set archiefnominatie (bewaren/vernietigen)
- **Support for all 9 afleidingswijzen:**
  1. afgehandeld — use case end date
  2. termijn — use case end date + procestermijn
  3. eigenschap — use value from a zaakeigenschap
  4. zaakobject — use date from a related object
  5. hoofdzaak — use main case date (for sub-cases)
  6. ingangsdatum_besluit — use decision effective date
  7. vervaldatum_besluit — use decision expiration date
  8. ander_datumkenmerk — use date from external registration
  9. gerelateerde_zaak (deprecated) — use related case date
- **Closed case restrictions:**
  - Prevent modification of closed cases (unless admin override)
  - Prevent adding new statuses after final status
  - Prevent changing resultaat after closure
- **Suspension and extension:**
  - zaak_opschorten — suspend a case (pause deadlines)
  - zaak_verlengen — extend a case's deadline
  - opschorting.eerdereOpschorting tracking
