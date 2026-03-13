# Open Klant -- Development

**Source**: https://open-klant.readthedocs.io/en/latest/development/index.html + GitHub repository

## Development Setup

Open Klant is open-source software welcoming community contributions.

### Tech Stack

- Python 3.12+
- Django + Django REST Framework
- drf-spectacular for OpenAPI schema generation
- PostgreSQL 14+ (database)
- Redis (cache + task broker)
- Celery (async tasks)
- Ruff (linting)
- pre-commit hooks
- CodeQL scanning

### Key Patterns

1. **Polymorphic Serializers**: Partij and Actor use custom `PolymorphicSerializer` with discriminator fields (`soort_partij`, `soort_actor`) that switch between sub-serializers.

2. **GegevensGroepType**: VNG pattern for flattening nested database fields while presenting them as nested objects in the API. Used for:
   - bezoekadres / correspondentieadres
   - contactnaam
   - actoridentificator
   - onderwerpobjectidentificator
   - bijlageidentificator
   - partijidentificator

3. **Expand Mechanism**: Custom `ExpandMixin` + `ExpandJSONRenderer` allows `?expand=` query parameter to inline related objects up to 2 levels deep.

4. **APIMixin**: Shared model mixin that adds created/updated timestamps and tracks the last API change.

5. **Structured Logging**: structlog with token info, entity UUIDs, and related entity UUIDs for audit trail.

6. **OpenTelemetry Metrics**: Counters for every CRUD operation on every entity type.

### Code Quality

- Ruff linter with pre-commit integration
- CodeQL security scanning
- CI: API design rules linter
- Test coverage tracking
