# KISS Configuration — Environment Variables

Source: https://github.com/Klantinteractie-Servicesysteem/KISS-frontend/blob/main/docs/installation/configuratie.md

## Authentication
- `OIDC_AUTHORITY` — URL of OIDC identity provider
- `OIDC_CLIENT_ID` — Client ID for OIDC
- `OIDC_CLIENT_SECRET` — Client secret for OIDC
- `OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM` — Claim used for employee identification
- `OIDC_MEDEWERKER_IDENTIFICATIE_TRUNCATE` — Truncate employee identification

## Database
- `POSTGRES_PASSWORD`, `POSTGRES_USER`, `POSTGRES_DB` — PostgreSQL credentials

## Organisation
- `ORGANISATIE_RSIN` — RSIN of the municipality

## Search (Elasticsearch)
- `ENTERPRISE_SEARCH_BASE_URL` — Enterprise Search / App Search URL
- `ENTERPRISE_SEARCH_PUBLIC_API_KEY` — Public API key
- `ENTERPRISE_SEARCH_PRIVATE_API_KEY` — Private API key
- `ELASTIC_BASE_URL`, `ELASTIC_USERNAME`, `ELASTIC_PASSWORD` — Elasticsearch credentials

## Customer & Contact Registration (Multiple Register Support)
Per register (REGISTERS__0__, REGISTERS__1__, etc.):
- `REGISTERS__N__IS_DEFAULT` — Whether this is the default register
- `REGISTERS__N__REGISTRY_VERSION` — `OpenKlant2` or `OpenKlant1`
- `REGISTERS__N__KLANTINTERACTIE_BASE_URL` — Open Klant 2.x base URL
- `REGISTERS__N__KLANTINTERACTIE_TOKEN` — Token for Open Klant 2.x
- `REGISTERS__N__ZAAKSYSTEEM_BASE_URL` — Zaaksysteem URL
- `REGISTERS__N__ZAAKSYSTEEM_API_KEY` / `API_CLIENT_ID` — Zaak credentials
- `REGISTERS__N__ZAAKSYSTEEM_DEEPLINK_URL` — Deep link URL for zaak detail
- `REGISTERS__N__ZAAKSYSTEEM_DEEPLINK_PROPERTY` — Property used for deeplinks (e.g. `identificatie`)

For OpenKlant1 registers:
- `REGISTERS__N__CONTACTMOMENTEN_BASE_URL` / `API_KEY` / `API_CLIENT_ID`
- `REGISTERS__N__KLANTEN_BASE_URL` / `CLIENT_ID` / `CLIENT_SECRET`
- `REGISTERS__N__INTERNE_TAAK_BASE_URL` / `CLIENT_ID` / `CLIENT_SECRET` / `OBJECT_TYPE_URL` / `TYPE_VERSION`

## Objects API Sources
- `AFDELINGEN_BASE_URL`, `AFDELINGEN_TOKEN`, `AFDELINGEN_OBJECT_TYPE_URL`
- `GROEPEN_BASE_URL`, `GROEPEN_TOKEN`, `GROEPEN_OBJECT_TYPE_URL`
- `INTERNE_TAAK_BASE_URL`, `INTERNE_TAAK_TOKEN`, `INTERNE_TAAK_OBJECT_TYPE_URL`

## External APIs
- `KVK_BASE_URL`, `KVK_API_KEY` — KvK (Chamber of Commerce)
- `HAAL_CENTRAAL_BASE_URL`, `HAAL_CENTRAAL_API_KEY` — BRP person queries
- `SDG_BASE_URL`, `SDG_API_KEY` — SDG invoervoorziening

## Email (Feedback)
- `EMAIL_HOST`, `EMAIL_PORT`, `EMAIL_USERNAME`, `EMAIL_PASSWORD`, `EMAIL_ENABLE_SSL`
- `FEEDBACK_EMAIL_FROM`, `FEEDBACK_EMAIL_TO`

## Feature Switches
- Feature for creating contact requests without Smoelenboek (staff directory)
- Feature for manual email address entry for contact requests
