# Open Klant -- GitHub README

**Source**: https://github.com/maykinmedia/open-klant

## Key Facts

- **Version**: 2.15.0
- **License**: EUPL
- **Language**: Python (95.0%), SCSS, HTML
- **Commits**: 924+ on master branch
- **Code Quality**: Build status, code coverage, CodeQL scanning
- **Linter**: Ruff

## Quick Start (Docker)

```bash
wget https://raw.githubusercontent.com/maykinmedia/open-klant/master/docker-compose.yml
docker-compose up -d --no-build
docker-compose exec web src/manage.py loaddata klantinteracties contactgegevens
docker-compose exec web src/manage.py createsuperuser
```

Access: `http://localhost:8000/`

## APIs

1. **Klantinteracties API (0.6.0)** -- VNG standard for customer interactions
2. **Contactgegevens API (1.1.1)** -- Custom API for basic contact data

Both have ReDoc and Swagger viewers available through the admin interface.

## Support

- Previous versions receive 6 months of support after new releases
- Version 1.0.0 contains legacy APIs (Klanten and Contactmomenten) no longer maintained by VNG

## Repository Structure

```
src/openklant/
  components/
    klantinteracties/     # Core Klantinteracties API component
      admin/              # Django admin configuration
      api/                # REST API layer
        filterset/        # Query parameter filters
        serializers/      # DRF serializers (polymorphic)
        viewsets/         # DRF viewsets
        urls.py
        validators.py
      models/             # Django models
        actoren.py        # Actor, Medewerker, GeautomatiseerdeActor, OrganisatorischeEenheid
        klantcontacten.py # Klantcontact, Betrokkene, Onderwerpobject, Bijlage
        partijen.py       # Partij, Vertegenwoordigden, CategorieRelatie, PartijIdentificator
        digitaal_adres.py # DigitaalAdres
        internetaken.py   # InterneTaak
        rekeningnummers.py # Rekeningnummer
        constants.py      # Enums and choices
      openapi.yaml        # OpenAPI 3.x specification
      tests/              # Test suite
    contactgegevens/      # Contactgegevens API component
      api/
      models.py
      openapi.yaml
      tests/
    token/                # Token authentication component
    utils/                # Shared utilities
```
