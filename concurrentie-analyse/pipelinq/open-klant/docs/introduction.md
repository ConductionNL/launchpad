# Open Klant -- Introduction

**Source**: https://open-klant.readthedocs.io/en/latest/introduction/index.html

## What is Open Klant?

Open Klant is a registration component for the storage and disclosure of customer data according to the Klantinteracties and Contactgegevens API specifications. It is developed by Maykin B.V. in collaboration with the municipalities of Amsterdam, The Hague, Utrecht, and VNG Realisatie.

Open Klant implements two APIs:

1. **Klantinteracties API (v0.6.0)** -- Based on VNG specifications for managing limited customer data and municipal interactions
2. **Contactgegevens API (v1.1.1)** -- A custom API (no VNG standard basis) for basic person/organisation contact details

The implementation is intended as both a reference implementation of the API specifications AND a production-ready component for government ICT landscapes.

## Semantic Information Model

The project includes a semantic information model defining entity relationships. Key entities include:

- **Partij** (Party) -- The central entity representing any person or organisation
- **Klantcontact** (Customer Contact) -- Records of interactions
- **Actor** -- Municipal employees, automated systems, or organisational units
- **DigitaalAdres** (Digital Address) -- Contact channels (email, phone, other)
- **InterneTaak** (Internal Task) -- Follow-up tasks from interactions
- **Onderwerpobject** (Subject Object) -- Links contacts to external objects (e.g. zaak)
- **Betrokkene** (Involved Party) -- Links parties to contacts with roles

## Versioning Policy

- New version releases every two months at the start of the month
- Major releases every two years
- Previous major version: 24 months of support after new major release
- Only the two most recent minor versions receive patches (6 months max for older of the two)
- Current version: 2.15.0 (February 2026)
- Version 1.0.x (legacy Klanten + Contactmomenten APIs) support extends to March 2026

## Technology Stack

- Python 3.12+
- Django + Django REST Framework
- PostgreSQL 14+
- Redis (cache + broker)
- Celery (task queue)
- Docker deployment
- OpenTelemetry observability
- structlog for structured logging
- drf-spectacular for OpenAPI schema generation

## License

EUPL (European Union Public Licence)

## Copyright

Copyright 2023, Maykin Media
