# OpenZaak Administration Manual

## Admin Interface

Open Zaak provides a Django-based admin interface (beheerinterface) for configuration and data management. Available at `https://<domain>/admin/`.

## Dashboard Groups

### Accounts
- **Gebruikers (Users)** — persons who can log into the admin; assigned to groups with permissions
- **Groepen (Groups)** — permission sets; users can belong to multiple groups

### Default Groups

| Group | Permissions |
|-------|------------|
| Admin | Full access to everything |
| API admin | Configure applications, API access, external API connections |
| Autorisaties admin | Configure applications and API access |
| Autorisaties lezen | Read-only view of application authorizations |
| Besluiten admin | CRUD on decisions and besluit-informatieobject relations |
| Besluiten lezen | Read-only decisions |
| Catalogi admin | CRUD on catalogi, zaaktypen, statustypen, resultaattypen, eigenschappen, roltypen, informatieobjecttypen, besluittypen, zaaktype-informatieobjecttypen |
| Catalogi lezen | Read-only catalog data |
| Documenten admin | CRUD on informatieobjecten, gebruiksrechten, document relations |
| Documenten lezen | Read-only documents |
| Zaken admin | CRUD on zaken, statussen, resultaten, eigenschappen, documenten, objecten, klantcontacten, betrokkenen, zaak-relaties |
| Zaken lezen | Read-only case data |

### API Autorisaties
- **Applicaties** — configure each consumer application's API access
- **Externe API credentials** — configure how Open Zaak authenticates to external APIs

### Gegevens (Data)
- **Besluiten** — view/manage decisions
- **Catalogi** — manage zaaktype catalogs (see Catalog Management)
- **Documenten** — view/manage informatieobjecten
- **Zaken** — view complete case dossiers

### Configuratie
- **Access attempts / Access logs** — brute-force protection and session logging
- **Notificatiescomponentconfiguratie** — configure which Notificaties API to use
- **Webhook subscriptions** — manage notification subscriptions
- **Websites** — configure the domain (must not be example.com)

### Logs
- **Failed notifications** — notifications that failed to send; manual retry available
- **Logging** — general log messages for troubleshooting

## User Management

### Creating Users
1. Navigate to Accounts > Gebruikers > Toevoegen
2. Set username and password (case-sensitive usernames, password strength rules apply)
3. Save and edit to configure:
   - **Stafstatus** — can log into admin
   - **Supergebruikerstatus** — has all permissions (use sparingly)
   - **Groepen** — assign to permission groups

## OpenID Connect (OIDC) SSO

Open Zaak supports SSO for the admin interface via OIDC:

1. User clicks "Inloggen met OIDC" on login screen
2. Redirected to OIDC provider (Keycloak, Azure AD, ADFS)
3. After authentication, redirected back to Open Zaak
4. Account created automatically; admin must assign groups

### Configuration
- Redirect URI: `https://<domain>/oidc/callback`
- Required: OpenID Connect client ID, secret, discovery endpoint
- Algorithm: RS256

### Provider-Specific Discovery URLs
- **ADFS:** `https://login.gemeente.nl/adfs/.well-known/openid-configuration`
- **Azure AD:** `https://login.microsoftonline.com/${tenantId}/v2.0`
- **Keycloak:** `https://keycloak.gemeente.nl/auth/realms/${realm}/.well-known/openid-configuration`

## List and Detail Views

### List View Features
1. Search field (by identificatie, UUID, etc.)
2. Filter panel (right side) — combinable (AND logic)
3. Sortable columns (click header, multi-column sort supported)
4. Bulk actions (select checkboxes, choose action)
5. Clickable links to detail views
6. Add button (top right)

### Detail View Features
1. Editable attribute fields (bold = required, normal = optional)
2. Related object lookup (magnifying glass icon)
3. Inline related objects (statussen, zaakobjecten, etc.)
4. History/audit log (admin changes + API audit trail)
5. Delete button (bottom left) with confirmation and cascade preview

## External API Configuration

### Authentication for External APIs
Navigate to API Autorisaties > Other external API credentials:
- **API-root** — base URL of external API
- **Label** — friendly name
- **Header key** — e.g., `X-Api-Key` or `Authorization`
- **Header value** — the API key

### NLX Integration
URL rewrites translate public URLs to NLX outway URLs:
- **From value** — public API URL prefix
- **To value** — NLX outway URL

## Data Export (Scripts)

The `dump_data.sh` script exports data to SQL or CSV:
```bash
DB_HOST=localhost DB_NAME=openzaak ./bin/dump_data.sh
# Export specific components:
./dump_data.sh zaken documenten
# CSV export:
./dump_data.sh --csv
```

Environment variables: DB_HOST, DB_PORT, DB_USER, DB_NAME, DB_PASSWORD, DUMP_FILE, TAR_FILE.
