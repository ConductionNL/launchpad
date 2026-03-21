# ZAC Access Control Policies

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/accessControlPolicies.md

## Implementation

Policies implemented using OPA Rego policy language files, deployed on startup to OPA server.
Some additional checks in Java/Kotlin backend code.

Frontend also uses access control rights to hide/show UI elements.

## Permission Matrix Summary

### Zaak Rechten

| Right | Raadpleger | Behandelaar | Coordinator | Recordmanager | Beheerder |
|-------|-----------|------------|-------------|--------------|----------|
| lezen | X | | | | |
| wijzigen | | X (open) | | X (+ afgehandeld) | |
| toekennen | | X (open) | | X (+ afgehandeld) | |
| behandelen | | X | | | |
| afbreken | | X | | | |
| heropenen | | | | X | |
| bekijkenZaakdata | | | | | X |
| verlengen | | X (open, not heropend/opgeschort, max 1x) | | | |
| opschorten | | X (open, not heropend/opgeschort) | | | |
| creeren_document | | X (open) | | | |
| versturen_email | | X (open) | | | |
| starten_taak | | X (CMMN-dependent) | | | |
| vastleggen_besluit | | X (open, not intake, has besluittypen) | | | |

### Taak Rechten

| Right | Raadpleger | Behandelaar | Coordinator | Recordmanager |
|-------|-----------|------------|-------------|--------------|
| lezen | X | | | |
| wijzigen | | X | | |
| toekennen | | X | | |
| creeren_document | | X (zaak+taak open) | | |

### Document Rechten

| Right | Raadpleger | Behandelaar | Recordmanager |
|-------|-----------|------------|--------------|
| lezen | X | | |
| wijzigen | | X (conditions) | X (extended) |
| verwijderen | | X (open, not definitief, unlocked) | X (unlocked) |
| vergrendelen | | X (open/heropend) | |
| ontgrendelen | | X (own locks) | X (all locks) |
| ondertekenen | | X (open/heropend, unlocked/own) | |
| downloaden | X | | |

### Werklijst Rechten

| Right | Role |
|-------|------|
| inbox | Coordinator |
| zaken_taken | Raadpleger |
| zaken_taken_verdelen | Coordinator |
| zaken_taken_exporteren | Beheerder |

### Overige Rechten

| Right | Role |
|-------|------|
| starten_zaak | Behandelaar |
| beheren | Beheerder |
| zoeken | Raadpleger |
