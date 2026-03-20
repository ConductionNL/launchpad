# ADR-001: OpenRegister as Universal Data Layer

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design
**Last updated:** 2026-03-19

## Context

Conduction builds multiple Nextcloud apps that all need persistent data storage. Rather than each app implementing its own database schema with Entity/Mapper patterns, OpenRegister provides a universal data layer with schema-driven storage, audit trails, and cross-app data sharing.

Apps that bypass OpenRegister create data silos, lose audit capabilities, and cannot participate in cross-app queries (e.g., OpenCatalogi federation, Pipelinq contact linking).

## Decision

- All Conduction apps MUST use OpenRegister for persistent data storage of domain objects.
- Apps MUST NOT create custom database tables for domain data (Entity/Mapper pattern for domain objects is prohibited).
- Apps MAY use Nextcloud's `IAppConfig` for app configuration settings (these are not domain objects).
- Apps MAY use Nextcloud's native tables for internal framework needs (e.g., job queues, caching) but MUST NOT use them for user-facing data.
- All data schemas MUST be defined as OpenRegister schemas and registered during app installation via repair steps.
- Apps SHOULD use the `ObjectService` for CRUD operations rather than direct mapper access.

## Consequences

- Spec authors MUST define data models as OpenRegister schemas, not as database migration plans.
- Design documents MUST NOT include custom database migration steps for domain data.
- Tasks MUST include schema registration in repair steps when introducing new data types.
- Cross-app data references become possible via shared registers.

## Exceptions

- `nldesign` — Stores CSS tokens as files and Nextcloud theme config, not as register objects. NL Design tokens are configuration, not domain data.
- `mydash` — Dashboard widget configuration uses `IAppConfig`. Widgets display data from other apps' registers.
- ExApp sidecar wrappers (openklant, openzaak, etc.) — These proxy external APIs and do not own data.
