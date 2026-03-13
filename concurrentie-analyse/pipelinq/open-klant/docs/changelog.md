# Open Klant -- Changelog

**Source**: https://open-klant.readthedocs.io/en/latest/changelog.html

## Version 2.15.0 (February 6, 2026)

**New Features:**
- Cloud Events implementation with zaak-gekoppeld and zaak-ontkoppeld events for Onderwerpobject operations
- Added `cascade` query parameter to DELETE method for OnderwerpObject to remove associated records
- Referentielijsten API integration improvements

**Maintenance:** Django 5.2.11, commonground-api-common 2.10.7, and other dependency upgrades

## Version 2.14.0 (December 1, 2025)

**New Features:**
- OpenTelemetry (OTel) implementation for application metrics and observability
- Referentielijsten API integration for Klantcontact.kanaal validation
- CSV export option for dump_data.sh script
- API design rules linter added to CI

**Bugfixes:** Fixed expand filter query parameter serialization, notification validation, and Sentry exception handling

## Version 2.13.0 (October 3, 2025)

**Notable Changes:**
- Phone number validation rules updated to support various formats
- New migration script `migrate_to_v2_phonenumbers` for phone number migration
- Enhanced exception logging with invalid_params and traceback details
- Environment variable support in YAML configuration via `value_from`

## Version 2.12.1 (September 4, 2025)

**Bugfix:** Ensured 8-digit BSNs receive leading zero in migration script

## Version 2.12.0 (August 28, 2025)

**Breaking Change:** OIDC configuration format restructured into separate `OIDCProvider` and `OIDCClient` models

**New Features:**
- Query parameters for searching klantcontacten by referred klantcontact
- Improved countrycode validation messaging

## Version 2.11.1 (August 8, 2025)

**Bugfixes:** Fixed migrate_to_v2 script for Partijen creation, added environment variable support for tokens, standardized DigitaalAdressen referentie

## Version 2.11.0 (August 5, 2025)

**New Features:**
- Added `verificatieDatum` attribute to DigitaalAdres with filtering options
- Multiple query parameters for date-based filtering

**Bugfixes:** Fixed cascading deletes affecting voorkeurs_digitaal_adres, expand filter null value handling, connection pooling configuration

## Version 2.10.0 (July 4, 2025)

**Breaking Change:** Requires PostgreSQL 14 or higher due to Django 5.2.3 upgrade

**New Features:**
- Structlog implementation for structured logging
- Log events for create, update, delete operations
- Database connection pooling environment variables (experimental)

## Version 2.9.0 (May 28, 2025)

**New Features:**
- Added `isStandaardAdres` to DigitaalAdres list endpoint filters
- Enhanced OAS documentation with help texts for query parameters

**Deprecated:** Field `anderePartijIdentificator` marked for removal in next major release

## Version 2.8.0 (May 14, 2025)

**New Features:**
- Added `DigitaalAdres.referentie` with filtering capability
- PartijIdentificator filters for multiple endpoints
- Made huisnummer nullable via API
- Django upgrade check integration

**Optimizations:** Performance improvements using select_related and prefetch_related; Python 3.12 support

## Version 2.7.0 (April 3, 2025)

**New Features:**
- Digitale adressen as expand option for betrokkenen endpoint
- Partij Identificatoren acceptance during Partij creation
- Notifications for InterneTaak and Partij

**Breaking Change:** Requires SITE_DOMAIN environment variable declaration

## Version 2.6.1 (March 21, 2025)

**Bugfix:** Ensured PartijIdentificator.partij nullable field

## Version 2.6.0 (March 4, 2025)

**New Features:**
- Updated PartijIdentificatoren ENUM values
- Enforced uniqueness constraints for Partij records
- Separate Dutch address fields alongside address lines
- Updated BAG ID and country code field validations

## Version 2.5.0 (January 28, 2025)

**New Features:**
- Setup configuration support for access tokens and OIDC admin authentication
- Multiple API response URL corrections
- Admin search improvements

## Version 2.4.0 (November 26, 2024)

**New Features:**
- Query parameters for filtering KlantenContact by Partij relationships
- Admin inlines for InterneTaak and Actor management
- `migrate_to_v2` command for upgrading from version 1.0.0
- Convenience endpoint `/maak-klantcontact` for single-request creation
- SoortDigitaalAdres ENUM implementation

## Version 2.3.0 (October 4, 2024)

**New Features:** Dynamic pagination with pageSize parameter

## Version 2.2.0 (September 5, 2024)

**New Features:**
- Query parameters for digitaleadressen endpoint
- Expand path from digitaleadressen to internetaken
- Actoren field in internetaken

**Deprecated:** Field `actor` replaced by `actoren`

## Version 2.1.0 (July 16, 2024)

**New Features:**
- SUBPATH mounting support
- Configurable Elastic APM service name
- Two-factor authentication (2FA) enabled by default
- Field `afgehandeld_op` for internetaken

**Breaking Changes:** User emails now unique; 2FA enabled by default

## Version 2.0.0 (March 15, 2024)

Initial release featuring Klantinteracties API implementation.

## Version 1.0.0 (February 16, 2023)

Legacy version with Klanten + Contactmomenten APIs (no longer maintained by VNG).

## Pre-releases (0.5.0 through 0.1.0)

Foundational features and NotificatieAPI support.
