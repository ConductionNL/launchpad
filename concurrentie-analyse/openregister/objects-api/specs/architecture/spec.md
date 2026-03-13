# Maykin Media Objects & Objecttypes API — Architecture

## Overview

The Maykin Media Objects ecosystem consists of **two separate Django applications** that work together:

1. **Objecttypes API** (port 9001) — Defines and manages object type schemas (like a schema registry)
2. **Objects API** (port 9002) — Stores and manages actual object instances

Both are Django/DRF applications with Django Admin as the only UI. There is **no custom frontend** — all user interaction happens through the Django Admin or the REST API.

## Technology Stack

| Component | Technology |
|-----------|-----------|
| Language | Python 3.12 |
| Framework | Django + Django REST Framework |
| Database | PostgreSQL 17 + PostGIS (for geo support) |
| Cache | Redis 7 |
| Task Queue | Celery (with Redis broker) |
| Auth | Token-based (API), Django sessions (admin), OIDC (SSO) |
| API Spec | OpenAPI 3.x (served via drf-spectacular + ReDoc) |
| License | EUPL 1.2 |
| Monitoring | OpenTelemetry (optional) |
| 2FA | TOTP + WebAuthn |

## Docker Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    PostgreSQL + PostGIS                   │
│  ┌──────────────┐  ┌──────────────────┐                 │
│  │ objecttypes  │  │     objects       │                 │
│  │   database   │  │    database       │                 │
│  └──────────────┘  └──────────────────┘                 │
└─────────────────────────────────────────────────────────┘
         │                       │
┌────────┴────────┐   ┌─────────┴─────────┐
│ Objecttypes API │   │   Objects API      │
│   (port 8000)   │   │   (port 8000)      │
│                 │   │                    │
│ - web           │   │ - web              │
│ - web-init      │   │ - web-init         │
│   (migrations)  │   │   (migrations)     │
│                 │   │ - celery worker    │
│                 │   │ - celery flower    │
└─────────────────┘   └────────────────────┘
                              │
                      ┌───────┴───────┐
                      │     Redis     │
                      │  (cache +     │
                      │   broker)     │
                      └───────────────┘
```

## Setup Configuration

Both APIs use YAML-based setup configuration (`django-setup-configuration`) that runs on first boot:
- Creates tokens, permissions, OIDC settings
- Idempotent — safe to re-run

## Key Design Decisions

1. **Separate deployments** — Objecttypes and Objects are independent services
2. **Objects API mirrors Objecttypes** — It has its own local copy of object types (synced or manually created)
3. **Token-based API auth** — Not session-based, each token identifies an application/organization
4. **PostGIS required** — Even if you don't use geometry, the database needs PostGIS extension
5. **Celery for async** — Background tasks (notifications, etc.) run via Celery workers
6. **CRS header required** — All API requests need `Content-Crs: EPSG:4326` header (GeoJSON compliance)
