# Objects API + Objecttypes API — Competitor Analysis

## Overview

- **Website:** https://objects-and-objecttypes-api.readthedocs.io/
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes
- **Summary:** Schema-driven generic object storage with REST APIs — define object types via JSON Schema, then store and query objects validated against those schemas. The most direct competitor to OpenRegister with a nearly 1:1 concept match.

## Codebase

- **Objects API:** https://github.com/maykinmedia/objects-api
- **Objecttypes API:** https://github.com/maykinmedia/objecttypes-api
- **ReadTheDocs:** https://objects-and-objecttypes-api.readthedocs.io/
- **OpenGem:** https://www.opengem.nl/producten/overige-registraties/

## Business Model

Developed by Maykin Media, commissioned by the Municipality of Utrecht. The software is free under the EUPL license. Revenue comes through the OpenGem initiative where Maykin provides SaaS hosting and support — municipalities never pay license fees, only for support and infrastructure. The API specifications are undergoing the VNG Realisatie standardization process, which would make them an official Dutch government standard. Development is co-funded by participating municipalities.

## Target Market

Dutch municipalities and government organizations that need flexible object storage for data that does not fit existing standard registries (e.g., laadpalen/charging stations, monuments, permits, trees, reports). Also targets organizations that want a generic, schema-validated data layer as part of their Common Ground architecture. Used as a backend for Open Formulieren form submissions and other applications that need structured data storage.

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or partners

## Key Features

- JSON Schema-based object type definitions with validation on create and update
- Schema versioning — object types can evolve with new schema versions while preserving backward compatibility
- Object versioning (records) — when an object is updated, previous states are preserved and retrievable
- Geo/geometry support — objects can include GeoJSON location data
- Attribute-based filtering — filter on any nested attribute in the JSON data (data_attrs query parameter)
- Case-insensitive partial string search (icontains operator)
- Date-aware filtering on data attributes
- Token-based authorization with per-objecttype read/write permissions
- Separation of concerns: Objecttypes API manages definitions, Objects API manages instances
- Django admin interface for management
- Docker/Kubernetes deployment with Helm charts available
- Notifications API integration for event-driven architectures

## Feature Comparison with OpenRegister

| Feature | Objects API | OpenRegister |
|---------|-----------|--------------|
| JSON Schema data modeling | Yes | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (attribute filtering only) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes (token per objecttype) | Yes |
| Audit trails | Partial (object versioning) | Yes |
| Multi-tenancy | No (single-tenant per deployment) | Yes |
| Webhooks / Events | Yes (via Notifications API) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | No (manual URL references) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | Yes (record history) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | Yes (VNG standard candidate) | Yes |

## Strengths

- Official VNG standardization track — if adopted, becomes the mandated way for Dutch government to store generic objects, giving it regulatory advantage
- Large existing adoption in Common Ground ecosystem — used as the backend for Open Formulieren submissions and other municipal workflows
- Clean separation of type definitions (Objecttypes API) and instances (Objects API) with built-in schema versioning and object history

## Weaknesses

- Very basic search capabilities — only attribute-based filtering with no full-text search, faceted search, or semantic search
- No first-class object relations — objects reference each other via URL strings rather than typed, navigable relationships
- No AI capabilities, no vector embeddings, no linked data support — purely a CRUD storage layer without intelligence

## Notes

The Objects API is the most direct competitor to OpenRegister in the Dutch government space. The concept mapping is nearly 1:1: Objecttypes API = OpenRegister Schemas, Objects API = OpenRegister Objects/Records. Both use JSON Schema for data modeling, both provide REST APIs, and both target the same municipal use cases. The key differentiators for OpenRegister are: (1) significantly richer search capabilities (full-text, faceted, semantic), (2) native Nextcloud integration providing a complete platform rather than a standalone API, (3) AI-powered features like vector embeddings, and (4) first-class object relations and linked data. The Objects API's advantage is its VNG standardization track and existing ecosystem adoption. OpenRegister should ensure compatibility with the Objects API specification to enable migration and interoperability.
