# OpenConnector — Feature Reference

**Application**: OpenConnector
**Description**: API gateway and integration platform — API lifecycle management, protocol translation, data synchronization, event bus.
**Software categories**: API management (`api-management`), Integration platform (iPaaS) (`ipaas`)
**Generated**: 2026-03-21

> Covers two Gartner Magic Quadrant categories: API Management (gateway, auth, rate limiting) and iPaaS (integration connectors, workflow orchestration). G2 categories: API Management + iPaaS.

## API management — Standard Features

*6 features defined. Evidence will grow as sync sources populate this category.*

- **[core]** `api-gateway` API gateway — Route, authenticate, rate-limit API requests
- **[core]** `api-key-management` API key management — Issue, revoke, rotate API keys and tokens
- **[core]** `rate-limiting` Rate limiting — Throttle requests per client/endpoint
- [standard] `api-documentation` API documentation — Auto-generate OpenAPI/Swagger docs
- [standard] `api-monitoring` API monitoring — Track uptime, latency, error rates per endpoint
- [standard] `api-versioning` API versioning — Manage multiple API versions simultaneously

## Integration platform (iPaaS) — Standard Features

*6 features defined. Evidence will grow as sync sources populate this category.*

- **[core]** `connector-library` Connector library — Pre-built connectors for common systems (BRP, BAG, KVK)
- **[core]** `data-sync` Data synchronization — Scheduled bidirectional sync between systems
- **[core]** `protocol-translation` Protocol translation — StUF-to-REST, SOAP-to-JSON, XML-to-JSON mapping
- [standard] `error-handling` Error handling — Retry, dead-letter queue, error notifications
- [standard] `event-bus` Event bus — CloudEvents, webhooks, pub/sub for real-time integration
- [standard] `transformation-engine` Transformation engine — Map, filter, enrich data between source and target

---

**Summary**: 0 TEC features, 0 evidence links, 0 additional (non-TEC) features, 12 standard features

*Generated from `concurrentie-analyse/intelligence.db` by `scripts/generate_app_features.py`*