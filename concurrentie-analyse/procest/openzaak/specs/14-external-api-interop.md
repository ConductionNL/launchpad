# Spec: External API Interoperability

## Feature: Consuming and Integrating with External ZGW API Providers

OpenZaak supports a federated model where different ZGW components can be provided by different vendors. Cases in one system can reference documents in another.

### Already in Procest

- ZGW API endpoint configuration (settings page)
- ZGW mapping service for URL translation
- Basic external API consumption

### Not Yet in Procest

- **External API credential management** — storing API keys/secrets for external APIs
- **NLX integration** — URL rewriting for government data exchange network
- **Cross-provider references** — a zaak referencing a document in a different DRC
- **External zaaktype references** — using catalogi from another ZTC provider
- **BAG/BRT integration** — linking zaakobjecten to Kadaster registrations
- **Haal Centraal integration** — BRP person data for roles
- **External selectielijst** — consuming selectielijst.openzaak.nl
- **Loose FK validation** — validating URLs to external ZGW endpoints
- **External API caching** — caching responses from external APIs for performance
- **LOOSE_FK_LOCAL_BASE_URLS** — distinguishing local from external URLs behind API gateways
- **Self-signed certificate support** — EXTRA_VERIFY_CERTS for non-standard CAs
