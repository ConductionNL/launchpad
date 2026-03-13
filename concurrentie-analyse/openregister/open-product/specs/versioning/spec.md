# Versioning (django-reversion)

## Summary

Open Product uses django-reversion to track all changes to product types and their related objects. The admin interface provides a "compare versions" view for reviewing changes, and all related objects are tracked together with their parent.

## Implementation

### Tracked Models (all @reversion.register decorated)
- **ProductType** (follows: verbruiksobject_schema, dataobject_schema, uniforme_product_naam, organisaties, locaties, contacten, content_elementen, externe_codes, links, parameters, bestanden, translations)
- **Product** (follows: eigenaren, producttype)
- **Thema** (follows: hoofd_thema)
- **Prijs** (follows: producttype, prijsopties)
- **All child entities**: ContentElement, ExterneCode, Link, Bestand, Actie, PrijsOptie, PrijsRegel, ContentLabel, ProductTypeTranslation, ContentElementTranslation

### Middleware
`reversion.middleware.RevisionMiddleware` wraps every request in a revision, so all changes within a single request form one version.

### Admin Integration
- `ADD_REVERSION_ADMIN = True` -- admin forms show version history
- `REVERSION_COMPARE_FOREIGN_OBJECTS_AS_ID = False` -- shows full foreign object details in diffs
- `REVERSION_COMPARE_IGNORE_NOT_REGISTERED = False` -- strict: all related objects must be registered
- Uses `reversion-compare` for side-by-side version comparison

## Already in OpenRegister
- Nextcloud file versioning for documents
- Basic object update tracking

## Not yet in OpenRegister
- **Full revision history** for all entities and their relations
- **Cascade version tracking** (child objects tracked with parent)
- **Version comparison** (side-by-side diff in admin)
- **Request-scoped revisions** (all changes in one API call = one version)
