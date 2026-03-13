# Spec: Authentication & Prefill — Open Formulieren vs Procest

## Feature: DigiD/eHerkenning Authentication and BRP/KvK Prefill

### Open Formulieren Authentication

**Supported methods:**

| Method | Protocol | Returns | Use Case |
|--------|----------|---------|----------|
| DigiD | SAML 2.0, OIDC | BSN | Citizen identification |
| DigiD Machtigen | OIDC | BSN (mandator + representative) | Acting on behalf of citizen |
| eHerkenning | SAML 2.0, OIDC | KvK number | Business identification |
| eHerkenning Bewindvoering | OIDC | KvK + legal guardian | Legal guardianship |
| eIDAS | SAML 2.0 | EU identity attrs | Cross-border (SDG) |
| Generic OIDC | OIDC | Configurable claims | Custom IdPs |
| Generic SAML | SAML 2.0 | Configurable attrs | Custom IdPs |

**Configuration:**
- Per-form authentication requirement toggle
- Multiple auth methods selectable per form
- Identity claim path configuration
- Certificate management for SAML

**Flow:**
1. Form loads → shows login options
2. User selects DigiD/eHerkenning → redirect to IdP
3. Authentication succeeds → BSN/KvK stored in session
4. Form continues with authenticated context

### Open Formulieren Prefill

**Available prefill sources:**

| Source | Data | Triggered By | Protocol |
|--------|------|-------------|----------|
| Haal Centraal BRP v2.0 | Name, address, DOB, gender, nationality, partners, children | BSN (DigiD) | REST |
| StUF-BG v3.1 | Same as BRP (legacy access) | BSN (DigiD) | SOAP |
| KvK / Haal Centraal HR | Company name, address, legal form, RSIN | KvK (eHerkenning) | REST |
| Objects API | Existing records/products | Object reference | REST |

**Prefill configuration per field:**
- Select prefill plugin (data source)
- Select prefill attribute (specific data point)
- Set default value (fallback)
- Mark as read-only or editable

**Advanced prefill (v3.0+):**
- Objects API prefill from existing records
- Enables renewal/update workflows
- Object reference passed via URL parameters

### Procest Authentication

**Current model:**
- Nextcloud native authentication (username/password)
- LDAP/Active Directory integration (via Nextcloud)
- SAML SSO (via Nextcloud)
- TOTP multi-factor (via Nextcloud)
- ZGW JWT authentication for API access (ZgwAuthMiddleware)

**No citizen-facing authentication** — Procest is for internal case workers only.

### Procest Prefill

**No prefill system** — Procest does not have form-based intake where prefill would apply.

**Case data sources:**
- ZGW Zaakeigenschappen (received from form submission or other source)
- OpenRegister objects
- Manually entered by case workers

### Gap Analysis

| Capability | Open Formulieren | Procest | Relevance to Procest |
|-----------|-----------------|---------|---------------------|
| DigiD | Yes | No | Low (internal tool) |
| eHerkenning | Yes | No | Low (internal tool) |
| eIDAS | Yes | No | Low (internal tool) |
| BRP prefill | Yes | No | Low (internal tool) |
| KvK prefill | Yes | No | Low (internal tool) |
| Objects API prefill | Yes | No | Medium (could enhance case creation) |
| Nextcloud SSO | No | Yes | N/A (different use case) |
| LDAP/AD | No | Yes (via NC) | N/A |
| ZGW JWT auth | No | Yes | N/A |

### Strategic Assessment

Authentication and prefill are **citizen-facing features** that Procest does not need in its current scope. These capabilities are relevant only if Procest were to add a citizen-facing portal, which is not recommended — that territory belongs to Open Formulieren and Open Inwoner.

**What Procest should do instead:**
1. Consume the data that Open Formulieren prefilled and submitted via ZGW APIs
2. Display citizen information (from Zaakeigenschappen/Rollen) in the case detail view
3. Leverage Nextcloud's authentication stack for internal users
4. Use ZGW JWT for API-level authentication with external ZGW systems

The authentication/prefill gap is **by design, not by deficiency.**
