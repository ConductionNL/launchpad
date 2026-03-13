# OpenZaak Client Development Guide

## Authentication

All endpoints are authorization-protected per the VNG Standard. Open Zaak uses JWT (JSON Web Tokens) with HS256 algorithm.

### JWT Payload Structure

```json
{
    "iss": "<Client ID>",
    "iat": 1602857301,
    "client_id": "<Client ID>",
    "user_id": "<unique user ID of the actual end user>",
    "user_representation": "<e.g. the name of the actual end user>"
}
```

### Usage Rules
- Sign with HS256 using the shared secret
- Include in every request: `Authorization: Bearer <jwt>`
- **Generate a new JWT for almost every call** — expires 1 hour after `iat`
- `user_id` and `user_representation` should match actual end user for audit purposes

### Code Examples Available For
- Python (using `pyjwt`)
- JavaScript (using `jsonwebtoken`)
- PHP (using `firebase/php-jwt`)
- Java (using `auth0/java-jwt`)

## CORS Configuration

For single-page applications (React, Angular):

### Production
```
CORS_ALLOWED_ORIGINS=https://my-app.example.com
```

### Development
```
CORS_ALLOW_ALL_ORIGINS=True
```

### Recommendation
Always use an API gateway/backend proxy to communicate with Open Zaak. Avoids CORS complexity and prevents credential leakage in frontend bundles. **Never store client ID/secret in frontend dist bundles.**

## Recipes

### Creating a Zaak, Document, and Relating Them

1. **Create Zaak:**
```json
POST /zaken/api/v1/zaken
{
    "zaaktype": "https://.../catalogi/api/v1/zaaktypen/<uuid>",
    "bronorganisatie": "123456782",
    "verantwoordelijkeOrganisatie": "123456782",
    "registratiedatum": "2024-01-15",
    "startdatum": "2024-01-15"
}
```

2. **Create Document:**
```json
POST /documenten/api/v1/enkelvoudiginformatieobjecten
{
    "bronorganisatie": "123456782",
    "creatiedatum": "2024-01-15",
    "titel": "Example document",
    "auteur": "Open Zaak",
    "inhoud": "<base64-encoded content>",
    "bestandsomvang": 1234,
    "bestandsnaam": "document.pdf",
    "taal": "nld",
    "informatieobjecttype": "https://.../catalogi/api/v1/informatieobjecttypen/<uuid>"
}
```

3. **Relate Them:**
```json
POST /zaken/api/v1/zaakinformatieobjecten
{
    "zaak": "<zaak-url>",
    "informatieobject": "<document-url>"
}
```

### Retrieving Documents for a Zaak

Key performance pattern: use threading/concurrency for parallel fetching.

1. Get all ZaakInformatieObjecten for the zaak
2. Collect all informatieobject URLs
3. Fetch all documents in parallel (ThreadPool in Python, Promise.all in JavaScript)

## Mandate-Based Case Management (Experimental)

### Concept
Cases can be initiated and managed by parties acting on behalf of others:
- **DigiD Machtigen** — individuals representing other individuals
- **eHerkenning** — organizations/employees representing companies or individuals
- **Ketenmachtiging** — chain of mandates between organizations

### Key Data Structure
Mandate info stored in `Rol.authenticatieContext`:
- `source`: "digid" or "eherkenning"
- `levelOfAssurance`: authentication strength
- `representee`: the party being represented
- `mandate`: mandate details including service IDs
- `actingSubject`: the person actually performing the action (eHerkenning)

### Roles
- `indicatieMachtiging: "gemachtigde"` — the party acting on behalf (authorizee)
- `indicatieMachtiging: "machtiginggever"` — the party being represented (representee)
- blank — the party acts for themselves

### Validation Rules
- If `representee` provided: `indicatieMachtiging` MUST be "gemachtigde" and `mandate` MUST be provided
- `natuurlijk_persoon` MUST use source "digid"
- `niet_natuurlijk_persoon` or `vestiging` MUST use source "eherkenning"

### Query Patterns

**Show my own cases (DigiD):**
```
GET /zaken?rol__betrokkeneIdentificatie__natuurlijkPersoon__inpBsn=<bsn>&rol__machtiging=eigen
```

**Show cases opened on my behalf:**
```
GET /zaken?rol__betrokkeneIdentificatie__natuurlijkPersoon__inpBsn=<bsn>&rol__machtiging=machtiginggever
```

**Show cases where I represent someone:**
```
GET /zaken?rol__betrokkeneIdentificatie__natuurlijkPersoon__inpBsn=<bsn>&rol__machtiging=gemachtigde
```

**Filter by level of assurance:**
```
GET /zaken?...&rol__machtiging__loa=urn:oasis:names:tc:SAML:2.0:ac:classes:MobileTwoFactorContract
```
