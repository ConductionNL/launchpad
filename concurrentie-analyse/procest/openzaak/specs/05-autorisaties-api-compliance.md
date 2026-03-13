# Spec: Autorisaties API Compliance

## Feature: Full VNG Autorisaties API Implementation

OpenZaak implements the Autorisaties API (v1.0.0) for managing application authorizations with scopes, types, and confidentiality levels.

### Already in Procest

- JWT-based authentication for ZGW API access
- Application registration (client ID + secret)
- Settings for ZGW API credentials (SettingsController.php, SettingsService.php)
- Nextcloud native RBAC (group-based access control)
- AcController.php for Autorisaties API interaction

### Not Yet in Procest

- Full Autorisaties API provider implementation (being an authorization provider, not just consumer)
- Application authorization management UI (managing which apps can access which types/scopes)
- Per-zaaktype scope configuration
- Per-informatieobjecttype scope configuration
- Per-besluittype scope configuration
- maxVertrouwelijkheidaanduiding per type per application
- heeftAlleAutorisaties flag
- Catalog-level authorization (Open Zaak extension)
- Client ID uniqueness enforcement (ac-001)
- Authorization specification validation (ac-002)
- Required fields validation per component (ac-003)
- JWT generation with user_id/user_representation for audit
- JWT expiry enforcement (1 hour default)
- External API credential management (authenticating to other ZGW providers)
- NLX URL rewriting for external APIs
