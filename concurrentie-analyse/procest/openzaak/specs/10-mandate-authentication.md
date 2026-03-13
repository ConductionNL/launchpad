# Spec: Mandate-Based Authentication (DigiD/eHerkenning)

## Feature: Recording Authentication Context and Mandates on Case Roles

OpenZaak experimentally supports DigiD Machtigen and eHerkenning mandates — recording who acts on behalf of whom when creating or managing cases.

### Already in Procest

- Basic role (rol) management on cases
- BetrokkeneType support (natuurlijk_persoon, niet_natuurlijk_persoon)
- Role linking with BSN and KvK identifiers

### Not Yet in Procest

- **authenticatieContext** on Rollen — recording DigiD/eHerkenning authentication details
- **DigiD Machtigen** — individuals representing other individuals
  - levelOfAssurance tracking
  - representee identification (BSN)
  - mandate service IDs
- **eHerkenning** — organizations representing themselves or others
  - Acting subject tracking
  - KvK-nummer and vestigingsNummer identification
  - contactpersoonRol for employee details
- **Ketenmachtiging** — chain mandates between organizations
- **indicatieMachtiging** — gemachtigde/machtiginggever/eigen classification
- **Query patterns for mandates:**
  - "Show my own cases"
  - "Show cases opened on my behalf"
  - "Show cases where I represent someone"
  - Level of assurance filtering
- **Validation rules:**
  - representee requires indicatieMachtiging = gemachtigde + mandate object
  - natuurlijk_persoon requires source = digid
  - niet_natuurlijk_persoon/vestiging requires source = eherkenning
- **Rol validity periods** — beginGeldigheid and eindeGeldigheid on roles

## Compliance Context

Mandate support is essential for government portals where citizens and businesses interact with case management systems. DigiD Machtigen is the standard for citizen-to-citizen representation; eHerkenning for business representation. Without this, Procest cannot serve as a citizen-facing portal backend.
