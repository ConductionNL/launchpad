# Admin Interface -- Open Klant Feature Spec

## Overview

Open Klant uses Django Admin as its sole UI. There is no custom frontend -- all management happens through the Django admin interface or via the REST APIs. The admin is branded "Open Klant Beheer" with a custom teal/cyan theme from Maykin Media.

## Navigation Structure

The admin header contains 7 top-level navigation tabs:
1. **Dashboard** -- home page with all model links
2. **Accounts** -- user management, groups, OIDC, MFA
3. **API auth** -- token authorizations
4. **Klantinteracties** -- core domain models
5. **Contactgegevens** -- separate contact registry
6. **Configuratie** -- system settings
7. **Logging** -- access logs and request logs
8. **Overige** -- misc (services, certificates, webhooks, categories)

## Admin Sections

### Accounts (7 models)
| Model | Add | Edit |
|---|---|---|
| Gebruikers (Users) | Yes | Yes |
| Groepen (Groups) | Yes | Yes |
| OIDC Providers | Yes | Yes |
| OIDC Clients | No | Yes |
| Session profiles | No | View only |
| TOTP devices | Yes | Yes |
| WebAuthn devices | Yes | Yes |

### API Auth (1 model)
| Model | Add | Edit |
|---|---|---|
| Token authorizations | Yes | Yes |

Token auth fields: identifier, token, contact_person, email, organization, application, administration.

### Klantinteracties (7 models)
| Model | List columns | Filters |
|---|---|---|
| Actoren | name | -- |
| Betrokkenen bij klantcontact | name | -- |
| Digitaal adress | address | -- |
| Interne taken | -- | -- |
| Klantcontacten | nummer, kanaal, indicatie_contact_gelukt, betrokkene_namen | indicatie_contact_gelukt, date hierarchy |
| Partij identificatoren | -- | -- |
| Partijen | nummer, naam, soort_partij, indicatie_actief | soort_partij, indicatie_actief |

### Contactgegevens (2 models)
| Model | Add | Edit |
|---|---|---|
| Organisaties | Yes | Yes |
| Personen | Yes | Yes |

### Configuratie (4 models)
| Model | Add | Edit |
|---|---|---|
| Applicatiegroepen | Yes | Yes |
| Notificatiescomponentconfiguratie | No | Yes (singleton) |
| Referentielijsten configuration | No | Yes (singleton) |
| Uitgaande request-logging configuratie | No | Yes (singleton) |

### Logging (3 models)
| Model | Add | Edit |
|---|---|---|
| Access attempts | No | View only |
| Access logs | No | View only |
| Uitgaande request-logs | No | View only |

### Overige (9 models)
| Model | Add | Edit |
|---|---|---|
| Access failures | No | View only |
| Autorisatiegegevens (JWT secrets) | Yes | Yes |
| Categorieen | Yes | Yes |
| Certificates | Yes | Yes |
| NLX configuration | No | Yes (singleton) |
| Services | Yes | Yes |
| Static devices | Yes | Yes |
| Versions | Yes | Yes |
| Webhook-abonnementen | Yes | Yes |

## Form Details

### Partij Form (most complex)
Main fields: UUID (auto), voorkeurs digitaal adres (autocomplete), voorkeurs rekeningnummer, nummer (integer, unique), interne notitie, soort partij (persoon/organisatie/contactpersoon), indicatie geheimhouding (onbekend/ja/nee), voorkeurstaal, indicatie actief.

Inline sections (10 total):
- Bezoekadres velden (nummeraanduiding ID, straatnaam, huisnummer, huisnummertoevoeging, postcode [1234 AB format], stad, adresregel 1-3, land [ISO 3166])
- Correspondentieadres velden (same structure)
- Persoon (voorletters, voornaam, voorvoegsel achternaam, achternaam)
- Categorieen relatie
- Contact persoon
- Organisatie
- Digitaal adress (betrokkene, soort [email/telefoon/overig], is_standaard, adres, omschrijving, referentie, verificatiedatum)
- Rekeningnummers
- Betrokkenen bij klantcontact
- Vertegenwoordigden
- Partij identificatoren

### Klantcontact Form
Main fields: nummer (integer), referentienummer (integer), kanaal, onderwerp, inhoud, indicatie contact gelukt, taal, vertrouwelijk, plaatsgevonden op (date+time), metadata (JSON).

Inline sections:
- Actor klantcontacten
- Betrokkenen bij klantcontact (UUID, partij [Select2 autocomplete], rol [vertegenwoordiger/klant], organisatienaam, initiator, bezoekadres, correspondentieadres, contactnaam)
- Onderwerpobjecten
- Bijlagen
- Interne taken

### Validation Rules Observed
- Nummer fields must be valid integers
- Postcode must match "1234 AB" format (with space)
- Land must be ISO 3166 country code (e.g., "NL" not "Nederland")
- Partij cannot represent itself (vertegenwoordigingen)
- Contactpersoon's werkte_voor_partij must be soort=organisatie

## UI Features
- Search bar on all list views
- Filter sidebar on partijen (soort_partij, indicatie_actief) and klantcontacten (indicatie_contact_gelukt)
- Date hierarchy navigation on klantcontacten
- Batch actions (delete selected)
- History tracking (Geschiedenis link on detail views)
- Select2 autocomplete widgets for foreign keys
- Theme toggle (auto/light/dark)
- OIDC login option ("Login with organization account")

## Comparison with Pipelinq

### Already in Pipelinq
- Nextcloud provides built-in user management, groups, and authentication
- OpenRegister provides object CRUD through its own admin

### Not yet in Pipelinq
- **Dedicated klantinteracties admin** with domain-specific forms and inlines
- **Select2 autocomplete** for FK relationships
- **Date hierarchy** navigation for temporal data
- **Faceted filtering** sidebar
- **Inline editing** of all related models from the parent form
- **History tracking** per object
- **Batch operations** (bulk delete)
- **Theme toggle** (light/dark mode)
- **Branded admin** with custom styling
