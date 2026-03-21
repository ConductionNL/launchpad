---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Access Control Policies -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements authorization and access control.
- **Product**: Dimpact ZAC
- **Category**: Security & Authorization
- **Relevance to Procest**: Fine-grained permissions are essential for government case management

## Architecture Overview
ZAC uses Open Policy Agent (OPA) for policy evaluation. Policies are written in Rego and deployed alongside the application. A `PolicyService` evaluates policies via REST calls to OPA. The system is transitioning from a legacy role/domain model to a new PABC (Policy-Based Access Control) integration.

Key components:
- `PolicyService` -- OPA evaluation client
- `OpaEvaluationClient` -- MicroProfile REST Client for OPA
- 7 Rego policy files in `resources/policies/`
- `assertPolicy()` utility function throws 403 on policy denial

## Data Model

### Application Roles (ZacApplicationRole)
| Role | Description |
|------|-------------|
| raadpleger | Viewer -- read access to cases, tasks, documents |
| behandelaar | Handler -- full CRUD on assigned items |
| coordinator | Coordinator -- bulk distribute/release, inbox management |
| recordmanager | Record Manager -- reopen cases, manage locked/closed documents |
| beheerder | Administrator -- system configuration, data export |

### Policy Domains

#### 1. Zaak Rechten (Case Permissions) -- 26 individual permissions
`lezen`, `wijzigen`, `toekennen`, `behandelen`, `afbreken`, `heropenen`, `bekijken_zaakdata`, `wijzigen_doorlooptijd`, `verlengen`, `opschorten`, `hervatten`, `creeren_document`, `toevoegen_document`, `koppelen`, `versturen_email`, `versturen_ontvangstbevestiging`, `toevoegen_initiator_persoon`, `toevoegen_initiator_bedrijf`, `verwijderen_initiator`, `toevoegen_betrokkene_persoon`, `toevoegen_betrokkene_bedrijf`, `verwijderen_betrokkene`, `toevoegen_bag_object`, `starten_taak`, `vastleggen_besluit`, `verlengen_doorlooptijd`, `wijzigen_locatie`

#### 2. Taak Rechten (Task Permissions) -- 5 permissions
`lezen`, `wijzigen`, `toekennen`, `creeren_document`, `toevoegen_document`

#### 3. Document Rechten (Document Permissions) -- 11 permissions
`lezen`, `wijzigen`, `verwijderen`, `vergrendelen`, `ontgrendelen`, `ondertekenen`, `toevoegen_nieuwe_versie`, `verplaatsen`, `ontkoppelen`, `downloaden`, `converteren`

#### 4. Werklijst Rechten (Worklist Permissions) -- 6 permissions
`inbox`, `ontkoppelde_documenten_verwijderen`, `inbox_productaanvragen_verwijderen`, `zaken_taken`, `zaken_taken_verdelen`, `zaken_taken_exporteren`

#### 5. Overige Rechten (Other Permissions) -- 3 permissions
`starten_zaak`, `beheren`, `zoeken`

#### 6. Notitie Rechten (Note Permissions)
Not analyzed in detail

### Policy Input Data

#### ZaakInput
```
user: { id, rollen, zaaktypen }
zaak: { open, zaaktype, opgeschort, verlengd, besloten, intake, heropend }
```

#### DocumentInput
```
user: { id, rollen, zaaktypen }
document: { definitief, vergrendeld, vergrendeld_door, ondertekend, zaak_open, zaaktype }
```

#### TaakInput
```
user: { id, rollen, zaaktypen }
taak: { open, zaaktype }
```

## Business Logic

### Policy Evaluation Pattern
```kotlin
// In every REST endpoint:
assertPolicy(policyService.readZaakRechten(zaak, loggedInUser).wijzigen)
```
- Builds input from current user + entity state
- Sends to OPA via REST
- Returns typed rights object
- `assertPolicy()` throws PolicyException (403) if false

### Key Authorization Rules

1. **Zaaktype filtering**: Users only see zaaktypes they are authorized for
2. **State-dependent permissions**: Many zaak permissions require `zaak.open`
3. **Role hierarchy**: raadpleger < behandelaar < coordinator < recordmanager < beheerder
4. **Lock-aware document permissions**: wijzigen/ondertekenen require unlocked or own lock
5. **Mutual exclusions**: verlengen requires !opgeschort && !verlengd && !heropend

### PABC Migration (Feature-Flagged)
- `configurationService.featureFlagPabcIntegration()` controls which IAM system is used
- Legacy: domain-based zaaktype access via zaakafhandelparameters
- New: PABC service provides group-to-zaaktype-to-role mappings

## Requirements (as observed)

1. Authorization is externalized to OPA -- all policy logic in Rego files
2. Five distinct policy domains with 51+ individual permissions
3. Permissions are context-sensitive (entity state, user roles, zaaktype)
4. Every REST endpoint explicitly checks policies before proceeding
5. Search results are filtered through policies per-item
6. Document permissions consider lock ownership
7. Feature flag enables gradual migration between IAM architectures

## IAM Architecture Transition (from Documentation)

### Current (Old) Architecture
- Keycloak for OIDC authentication
- Two disjunct authorization principles:
  1. Authorised zaaktypes via domain roles (e.g., `domein_sociaal`) in Keycloak
  2. Application roles mapped in Keycloak
- Special `domein_elk_zaaktype` grants access to all zaaktypes
- All users with a role also need all lower-level roles

### New Architecture (PABC Integration)
- Behind `FEATURE_FLAG_PABC_INTEGRATION` feature flag
- Adds PABC (Platform Autorisatie Beheer Component)
- Allows **different application roles per zaaktype per user**
- Flow: Keycloak JWT -> functional roles -> PABC authorisation mappings -> OPA policy checks
- Domain concept becomes PABC-internal only
- Entity types abstracted beyond zaaktypes for future extensibility

### Role Hierarchy (cumulative)
1. Raadpleger (viewer)
2. Behandelaar (handler) -- needs Raadpleger too
3. Coordinator -- needs Raadpleger + Behandelaar
4. Recordmanager -- needs all above
5. Beheerder (admin) -- needs all above

## Comparison Notes
- ZAC's OPA-based authorization is very sophisticated. Procest uses simpler role-based access.
- The 51+ permission model is comprehensive but complex to configure.
- The externalized policy engine allows hot-reloading policy changes without redeployment.
- The PABC migration shows the challenge of evolving authorization models.
- The new IAM architecture with per-zaaktype role differentiation is powerful but adds infrastructure (PABC + Keycloak)
- Cumulative role hierarchy is simple but inflexible -- cannot have admin without all other roles
- Procest could benefit from a similar policy-as-code approach but might start simpler.
- Nextcloud's built-in group/permission system could cover many of these scenarios more simply.
