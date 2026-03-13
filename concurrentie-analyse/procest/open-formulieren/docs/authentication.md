# Open Formulieren — Authentication (DigiD, eHerkenning, eIDAS)

## Overview

Open Formulieren has a comprehensive authentication plugin system that supports all major Dutch government authentication methods. Authentication is optional per form — administrators can require it or leave forms anonymous.

## Supported Authentication Methods

### DigiD (Citizens)

- **Protocol:** SAML 2.0 or OpenID Connect
- **Result:** BSN (Burgerservicenummer) of the authenticated citizen
- **Use case:** Forms requiring citizen identification (permits, benefits, etc.)
- **DigiD Machtigen:** Allows logging in on behalf of another person (mandate/proxy)

### eHerkenning (Businesses)

- **Protocol:** SAML 2.0 or OpenID Connect
- **Result:** KvK number (Chamber of Commerce registration number)
- **Use case:** Forms for business-related requests (permits, subsidies)
- **eHerkenning Bewindvoering:** Allows legal guardians to act on behalf of companies

### eIDAS (European)

- **Protocol:** Cross-border authentication for EU citizens
- **Result:** European identity attributes
- **Use case:** SDG (Single Digital Gateway) compliance for cross-border services

### OpenID Connect (Generic)

- **Configuration:** Supports any OIDC provider
- **Claim mapping:** Configurable claim paths for identity attributes
- **Flexible:** Can be used for custom identity providers

### SAML 2.0 (Generic)

- **Configuration:** Supports any SAML 2.0 Identity Provider
- **Attribute mapping:** Configurable attribute statements
- **Certificate-based:** Mutual TLS and XML signature verification

## Authentication Flow

1. Form is loaded in the SDK
2. If authentication is required, login options are presented
3. User clicks login → redirected to identity provider
4. After successful authentication, redirected back to the form
5. Identity attributes (BSN, KvK number) are stored in the session
6. Prefill plugins use these attributes to fetch data from national registries

## Configuration

### Identity Settings (eHerkenning)
- **Legal subject claim path:** Configure where the KvK number (or RSIN) is found in the identity claims
- **Representation claims:** For bewindvoering (legal guardianship) scenarios

### DigiD Machtigen + eHerkenning Bewindvoering
- Login on behalf of someone else
- Both the representative and the represented person's identity are captured
- Separate claim paths for representative vs. represented

## Comparison with Procest

| Feature | Open Formulieren | Procest |
|---------|-----------------|---------|
| DigiD | Yes (SAML + OIDC) | No |
| eHerkenning | Yes (SAML + OIDC) | No |
| eIDAS | Yes | No |
| DigiD Machtigen | Yes | No |
| eHerkenning Bewindvoering | Yes | No |
| Nextcloud SSO | No | Yes (native) |
| LDAP/AD | No | Yes (via Nextcloud) |
| ZGW JWT auth | No | Yes |
| Multi-factor auth | Via identity provider | Via Nextcloud (TOTP) |

### Analysis

Open Formulieren's authentication stack is designed for **citizen-facing** interactions. It handles the complex Dutch government identity landscape (DigiD, eHerkenning) that Procest does not need because Procest is an **internal** case management tool for municipal employees. These are complementary rather than competing features.

If Procest were to add a citizen-facing portal, it would need to implement DigiD/eHerkenning — but this would be better handled by integrating with Open Formulieren or a dedicated citizen portal (like Open Inwoner) rather than building it natively.
