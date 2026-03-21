# ZAC System Context

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/systemContext.md

## ZAC Runtime Components

| Component | Description | Usage |
|-----------|-------------|-------|
| ZAC Application | Backend + frontend in WildFly | Core application |
| OfficeConverter | Office document conversion | Convert .docx etc. to PDF |
| Open Policy Agent (OPA) | Policy engine | Access control policies |
| Solr | Search engine | Zaak/task/document indexing |

## Common Ground Components

| Component | ZAC Usage | API Used |
|-----------|-----------|----------|
| Objecten API | Productaanvragen storage | Objects API |
| Open Formulieren | Citizen form submission (indirect) | n/a (via productaanvraag flow) |
| Open Klant | Customer/company contact data | Klantinteracties API |
| Open Notificaties | Event notifications | Notificaties API (consumer) |
| Open Zaak | Zaken, documents, zaaktypes, besluiten | Besluiten, Documenten, Zaken, Catalogi APIs |
| Open Archiefbeheer | Archive/record destruction | Triggers zaak destruction |
| PABC | Authorization management | PABC Authorisation API |
| Keycloak | Identity management | OIDC, Keycloak API |
| OTEL Collector | Telemetry | OTLP protocol |

## External Services

| Service | ZAC Usage | API Used |
|---------|-----------|----------|
| Haal Centraal BAG | Address/location data | IMBAG API |
| Haal Centraal BRP | Personal data (citizens) | BRP Personen Bevragen API |
| KVK | Company data | Vestigingsprofiel + Zoeken API |
| SMTP Server | Send emails | SMTP Protocol |
| SmartDocuments | Document creation wizard | SmartDocuments REST API |
| MS Office Desktop Apps | Document editing | WebDAV Protocol |

## KVK Integration Details

Supported organisation identifiers in ZGW ZRC API:

| Organisation type | Identifier | Read | Write |
|-------------------|-----------|------|-------|
| Vestiging (branch) | KVK nummer + vestigingsnummer | Yes | Yes |
| Vestiging (legacy) | vestigingsnummer only | Yes | No |
| Rechtspersoon | KVK nummer | Yes | Yes |
| Rechtspersoon (legacy) | RSIN | Yes | No |

Note: ZAC only reads from the Klantinteracties API, does not write to it.
