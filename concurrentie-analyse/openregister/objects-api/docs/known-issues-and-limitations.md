# Objects API — Known Issues and Limitations

## Open Issues (59 total as of 2026-03-12)

### Critical / Active Development
- **#730**: Preparing release 4.0.0 (merging Objecttypes into Objects)
- **#564**: Combine Objecttypes API into Objects API (approved, owned by Utrecht)
- **#738**: Permission admin field-based authorization form needs update for local objecttypes

### Feature Requests (Approved/Waiting)
- **#565**: Export/Import Object Types with UUID retention (approved, Dimpact + Rotterdam)
- **#673**: Restrict admin access by object type (Amsterdam, waiting for approval)
- **#518**: Search for objectTypes in permission dropdown (Den Haag, waiting)
- **#405**: Extended filter on Object notification subscriptions (Den Haag, blocked)
- **#109**: Bulk import datasets via API
- **#152**: Custom unique identifiers for objects
- **#151**: Multiple geo-coordinate systems support

### Known Bugs
- **#722**: Translations broken due to LocaleMiddleware disabled
- **#281**: Notification channels page doesn't list resources
- **#297**: Wrong username generated when login with org account
- **#271**: JSON schema field ordering changes when storing

### Performance
- **#48**: Performance of filtering through 1 million objects
- **#200**: Proper indexing for quick retrieval
- **#227**: Insight in performance after applying indexes
- **#149**: Stress testing needed

### Architecture Decisions Needed
- **#120**: Endpoints with trailing slashes
- **#90**: Query param syntax confusion for data attribute filtering
- **#110**: Migration guidelines from objecttype version N to N+1
- **#302**: Dynamic field types undesirable for Java/Mendix developers

### Ecosystem Integration Wishes
- **#64**: Connection with ZaakObjecten
- **#63**: Connection with Amsterdam Schema
- **#62**: Connection with data.overheid.nl
- **#206**: ZaakType/ResultaatType/StatusType referencing ObjectTypes

## Key Limitations

1. **Two separate applications** — Objects API and Objecttypes API are separate Django apps requiring separate deployments (being fixed in v4.0.0)
2. **Single CRS** — Only EPSG:4326 supported, no CRS transformation
3. **No bulk import** — No batch/bulk API for importing large datasets
4. **No custom identifiers** — Objects identified only by UUID, no user-defined unique keys
5. **Performance at scale** — Filtering through millions of objects not yet benchmarked/optimized
6. **Limited admin search** — Admin search can be disabled for performance; no full-text search engine
7. **No UI frontend** — Only Django admin; no end-user facing frontend application
8. **PostgreSQL only** — No support for other databases
9. **Python only** — Reference implementation is Python/Django; no alternative implementations
10. **English-only interface** — Does not comply with Dutch API Strategy recommendation for Dutch interfaces
