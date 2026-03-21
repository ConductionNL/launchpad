# ZAC IAM Architecture

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/iamArchitecture.md

## Application Roles

| Role | Description (Dutch) |
|------|---------------------|
| Raadpleger | View-only: can read zaken, taken, documents |
| Behandelaar | Case handler: full rights on werklijsten, zaken, taken, documents |
| Coordinator | Work distributor: distributes work from werklijsten, reads zaken/taken |
| Recordmanager | Can read zaken/taken, extra rights for documents and completed zaken |
| Beheerder | Functional admin: access to admin screens, configuration management |

Roles are cumulative: each higher role needs all lower roles assigned too.

## Current (Old) IAM Architecture

- Keycloak for OIDC authentication
- Keycloak API for groups and users
- OPA for policy management
- Two disjunct authorization principles:
  1. **Authorised zaaktypes** (via domains/domein roles in Keycloak)
  2. **Application roles** (mapped in Keycloak)

Domain roles (e.g., `domein_sociaal`) grant access to specific zaaktypes. Special `domein_elk_zaaktype` grants access to all zaaktypes.

## New IAM Architecture (PABC Integration)

Behind `FEATURE_FLAG_PABC_INTEGRATION` feature flag. Adds **PABC** (Platform Autorisatie Beheer Component):

### Components

| Component | Manages | Purpose |
|-----------|---------|---------|
| Keycloak | Users, groups, functional roles | Authentication + user-role mapping |
| PABC | Domains, entity types, authorisation mappings | Fine-grained role-per-zaaktype authorization |
| ZAC | Application roles, policies | OPA-based permission checks |

### Key Difference

New architecture allows **different application roles per zaaktype per user**. A user can be a Behandelaar for zaaktype A but only a Raadpleger for zaaktype B.

### Authorization Flow

1. User logs in -> Keycloak returns functional roles in JWT
2. ZAC retrieves authorisation mappings from PABC for those functional roles
3. PABC returns: zaaktype X -> application role Y mappings
4. ZAC uses these mappings for OPA policy checks

### Internal Endpoints

ZAC has internal endpoints (`/rest/internal/*`) secured with API key (`X-API-KEY` header) for system integrations (cron jobs, scripts).
