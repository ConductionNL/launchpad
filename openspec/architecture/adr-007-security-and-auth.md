# ADR-007: Security and Authentication

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-19

## Context

Conduction apps handle government data that may include personal information (PII), case files, and citizen communications. Security breaches in government software have severe legal and public trust consequences. The Dutch BIO (Baseline Informatiebeveiliging Overheid) sets minimum security requirements.

## Decision

### Authentication
- Apps MUST use Nextcloud's built-in authentication system exclusively.
- Apps MUST NOT implement custom login flows, session management, or token generation.
- Apps MUST NOT store passwords or authentication credentials in OpenRegister or any app-level storage.
- Public (unauthenticated) endpoints MUST be explicitly annotated and limited to read-only operations where possible.

### Authorization
- Apps MUST respect Nextcloud's group and permission system for access control.
- Admin-only operations MUST check group membership via `IGroupManager::isAdmin()` on the backend.
- Apps MUST NOT rely on frontend-only authorization checks (e.g., `OC.isAdmin` without backend verification).
- Multi-tenant data isolation MUST be enforced at the API/service level, not just the UI level.

### Data Protection
- Apps MUST NOT log or expose PII in error messages, API responses, or debug output.
- Sensitive configuration (API keys, external service credentials) MUST be stored via `IAppConfig` with the sensitive flag.
- File uploads MUST be validated for type and size before storage.
- SQL injection, XSS, and CSRF protections are provided by Nextcloud's framework and MUST NOT be bypassed.

### Security Headers
- API responses MUST NOT include sensitive internal information (stack traces, SQL queries, internal paths) in production.
- CORS headers MUST be explicitly configured — apps MUST NOT use `Access-Control-Allow-Origin: *` on authenticated endpoints.

## Consequences

- Spec authors MUST declare whether endpoints are public or authenticated.
- Spec scenarios involving access control MUST include both authorized and unauthorized cases.
- Design documents MUST explicitly address authorization for each endpoint.
- Tasks MUST NOT include shortcuts that bypass security (e.g., `--no-verify`, disabling CSRF).

## Exceptions

- MCP endpoints use Basic Auth for machine-to-machine communication, which is acceptable over localhost in development but MUST use HTTPS in production.
- Public intake forms (citizen-facing) are intentionally unauthenticated but MUST implement rate limiting.
