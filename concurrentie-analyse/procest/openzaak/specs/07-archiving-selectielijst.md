# Spec: Archiving and Selectielijst Integration

## Feature: Full VNG Archiving Model with Selectielijst

The archiving model is one of the most complex and compliance-critical aspects of the ZGW standard. OpenZaak fully implements the national Selectielijst integration for automated archive date calculation.

### Already in Procest

- Basic archiefnominatie (bewaren/vernietigen) on resultaattypen
- Archiefactietermijn (retention period) storage
- Basic archiving status tracking

### Not Yet in Procest

- **Selectielijst API integration** — connecting to selectielijst.openzaak.nl for archiving classes
- **Selectielijstklasse** validation on ResultaatType (ztc-002)
- **Procestermijn** model — nihil, bestaansduur_procesobject, ingeschatte_bestaansduur_procesobject, vast_te_leggen_datum, samengevoegd_met_bewaartermijn
- **Afleidingswijze** configuration — all 9 derivation methods for archive start date
- **brondatumArchiefprocedure** — complete configuration model with conditional required fields
- **Automatic archiefactiedatum calculation** on case closure (brondatum + archiefactietermijn)
- **Archiefstatus** management (nog_te_archiveren, gearchiveerd, gearchiveerd_procestermijn_onbekend, overgedragen)
- **Compatibility matrix enforcement** — ensuring afleidingswijze matches procestermijn
- **Destruction list generation** — identifying cases ready for destruction
- **Integration with Open Archiefbeheer** — external archive management
- **Zaakdossier formation** — all informatieobjecten of a case = zaakarchief
- **Transfer to external archive** — marking cases as overgedragen

## Compliance Risk

The archiving model is a key compliance requirement for Dutch municipalities. Incorrect archiving can lead to:
- Premature destruction of legally required records
- Failure to destroy records that should be destroyed (GDPR implications)
- Non-compliance with the Archiefwet (Dutch Archives Act)

This is an area where Procest must achieve full compliance to be viable for municipal use.
