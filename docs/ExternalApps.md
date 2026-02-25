# External Apps Overview

This document describes all external apps in the workspace, including custom Nextcloud apps, ExApps (AppAPI-based external applications), and third-party service integrations.

**Nextcloud version:** 32.0.5
**Docker Compose:** `openregister/docker-compose.yml`

## Custom Nextcloud Apps

Apps developed in-house, mounted as custom_apps via Docker volumes.

| App | Directory | Version | Status | Mounted |
|-----|-----------|---------|--------|---------|
| **Open Register** | `openregister` | 0.2.9-beta.39 | Enabled | Yes |
| **OpenCatalogi** | `opencatalogi` | 0.7.7-beta.1 | Enabled | Yes |
| **Software Catalogus** | `softwarecatalog` | 0.1.137-beta.11 | Disabled (not enabled) | Yes |
| **Open Connector** | `openconnector` | 0.2.2 | Not mounted | No |
| **DocuDesk** | `docudesk` | 1.0.0 | Not mounted | No |
| **NL Design System** | `nldesign` | 0.1.0 | Enabled | Yes |
| **MyDash** | `mydash` | 1.0.0 | Enabled | Yes |
| **OpenTalk** | `opentalk` | 0.1.0 | Enabled | Yes |
| **Valtimo** | `valtimo` | 0.1.0 | Not enabled | Yes |
| **OpenZaak** | `openzaak` | 0.1.0 | Not enabled | Yes |
| **OpenKlant** | `openklant` | 0.1.0 | Not enabled | Yes |

**Notes:**
- `openconnector` and `docudesk` exist in the workspace but are not mounted as Docker volumes in the Nextcloud container. Add volume mounts in `docker-compose.yml` to use them.
- `softwarecatalog` is mounted but not enabled. Run `occ app:enable softwarecatalog` to activate.
- `valtimo`, `openzaak`, and `openklant` are mounted but not yet enabled. Enable with `occ app:enable <appid>` after starting their backend services.

## NC App Store Integrations

Traditional PHP apps installed from the Nextcloud App Store.

| App | Version | Status | Notes |
|-----|---------|--------|-------|
| **integration_openproject** | 2.11.0 | Enabled | Links NC files with OpenProject work packages via OAuth |
| **xwiki** | 1.0.0 | Installed, disabled | Incompatible with NC 32 - awaiting update from XWiki maintainers |
| **app_api** | 32.0.0 | Enabled | AppAPI framework for managing ExApps |
| **webhook_listeners** | 1.3.0 | Enabled | Required dependency for Flow/Windmill |

## ExApps (AppAPI External Applications)

ExApps run as sidecar Docker containers managed by Nextcloud's AppAPI framework via HaRP (Deploy Daemon).

**Deploy Daemon:** HaRP (`ghcr.io/nextcloud/nextcloud-appapi-harp:release`) on port 8780

### ExApp Status

| ExApp | Container | Status | Notes |
|-------|-----------|--------|-------|
| **n8n** | `openregister-exapp-n8n` | Running (unhealthy) | Workflow automation, needs health check investigation |
| **Ollama** | `openregister-exapp-ollama` | Running (unhealthy) | Local LLM inference, needs health check investigation |
| **Open WebUI** | `openregister-exapp-openwebui` | Created (not started) | Chat interface for LLMs |
| **Flow (Windmill)** | `nc_app_flow` | Crash-looping | Windmill OSS limitation: cannot change default credentials during init |

### ExApp Access (when healthy)

- n8n: `http://localhost:8080/index.php/apps/app_api/proxy/n8n`
- Ollama: `http://localhost:8080/index.php/apps/app_api/proxy/ollama/api/tags`
- Open WebUI: `http://localhost:8080/index.php/apps/app_api/proxy/open_webui`

## Third-Party Service Integrations

External services running as Docker containers alongside Nextcloud. Enable using Docker Compose profiles.

### OpenProject (Project Management)

| | |
|-|-|
| **Status** | Running, healthy |
| **Profile** | `--profile openproject` or `--profile integrations` |
| **Container** | `openregister-openproject` |
| **Access** | http://localhost:8085 |
| **Image** | `openproject/openproject:15` |
| **NC App** | `integration_openproject` 2.11.0 (enabled) |
| **Website** | https://www.openproject.org |

Links Nextcloud files/folders with OpenProject work packages. Uses OAuth-based authentication between platforms.

```bash
# Start OpenProject
docker-compose --profile openproject up -d

# NC integration app is already installed and enabled
```

### XWiki (Wiki Platform)

| | |
|-|-|
| **Status** | Running, healthy |
| **Profile** | `--profile xwiki` or `--profile integrations` |
| **Container** | `openregister-xwiki` |
| **Access** | http://localhost:8086 |
| **Image** | `xwiki:lts-postgres-tomcat` |
| **Database** | `xwiki` (PostgreSQL, shared with Nextcloud) |
| **NC App** | `xwiki` 1.0.0 (installed but cannot enable - incompatible with NC 32) |
| **Website** | https://www.xwiki.org |

XWiki server is running and accessible. The Nextcloud integration app is installed but cannot be enabled because it only supports up to ~NC 30. The XWiki server itself works independently at http://localhost:8086. Requires the "Nextcloud Application" extension on the XWiki side (`xwiki-contrib/application-nextcloud`) for full integration.

```bash
# Start XWiki
docker-compose --profile xwiki up -d
```

### Open-Xchange (Email & Groupware)

| | |
|-|-|
| **Status** | Not running - image unavailable |
| **Profile** | `--profile ox` or `--profile integrations` |
| **Container** | `openregister-open-xchange` |
| **Access** | http://localhost:8087 (when running) |
| **Image** | `appsuite/appsuite-core:latest` |
| **NC App** | None (integration is on the OX App Suite side) |
| **Website** | https://www.open-xchange.com |

The OX Docker image is not publicly available and requires registry credentials from Open-Xchange. The service definition exists in docker-compose but cannot be started without access.

Open-Xchange integration works from the OX side: it embeds Nextcloud file-picker dialogs into OX App Suite. Users can browse Nextcloud files when composing emails and save email attachments to Nextcloud via WebDAV/OAuth.

### Valtimo (BPM & Case Management)

| | |
|-|-|
| **Status** | Not running (not started) |
| **Profile** | `--profile valtimo`, `--profile commonground`, or `--profile integrations` |
| **Container** | `openregister-valtimo` |
| **Access** | http://localhost:8088 |
| **Image** | `ritense/gzac-backend:latest` |
| **Database** | `valtimo` (PostgreSQL, shared with Nextcloud) |
| **NC App** | `valtimo` 0.1.0 (mounted, not enabled) |
| **Website** | https://www.valtimo.nl |
| **Docs** | https://docs.valtimo.nl |

Valtimo is a BPM (Business Process Management) and case management platform built on Camunda. Part of the Dutch Common Ground ecosystem. Uses BPMN/DMN workflows, ZGW API integration, and Keycloak for authentication.

**Key features:**
- BPMN workflow engine (Camunda-based)
- Case management with ZGW API support
- Document generation and management
- Form.io-based forms
- Keycloak SSO integration

```bash
# Create database first
docker exec openregister-postgres psql -U nextcloud -c "CREATE DATABASE valtimo;"

# Start Valtimo
docker-compose --profile valtimo up -d

# Enable NC app
docker exec nextcloud php occ app:enable valtimo
```

### OpenZaak (ZGW Case Management API)

| | |
|-|-|
| **Status** | Not running (not started) |
| **Profile** | `--profile openzaak`, `--profile commonground`, or `--profile integrations` |
| **Container** | `openregister-openzaak` |
| **Access** | http://localhost:8089 |
| **Image** | `openzaak/open-zaak:latest` |
| **Database** | `openzaak` (PostgreSQL, shared with Nextcloud) |
| **NC App** | `openzaak` 0.1.0 (mounted, not enabled) |
| **Website** | https://openzaak.org |
| **Docs** | https://open-zaak.readthedocs.io |

OpenZaak is the reference implementation of the Dutch ZGW (Zaakgericht Werken) APIs. It provides a complete backend for case management following government standards. Authentication via JWT (HS256).

**ZGW APIs provided:**
- **Zaken API** - Case management (create, update, close cases)
- **Documenten API** - Document management (could integrate with Nextcloud Files)
- **Catalogi API** - Case type catalogs and definitions
- **Besluiten API** - Decision management
- **Autorisaties API** - Authorization and access control

```bash
# Create database first
docker exec openregister-postgres psql -U nextcloud -c "CREATE DATABASE openzaak;"

# Start OpenZaak
docker-compose --profile openzaak up -d

# Enable NC app
docker exec nextcloud php occ app:enable openzaak
```

### OpenKlant (Customer Interaction Registry)

| | |
|-|-|
| **Status** | Not running (not started) |
| **Profile** | `--profile openklant`, `--profile commonground`, or `--profile integrations` |
| **Container** | `openregister-openklant` |
| **Access** | http://localhost:8090 |
| **Image** | `maykinmedia/open-klant:latest` |
| **Database** | `openklant` (PostgreSQL, shared with Nextcloud) |
| **NC App** | `openklant` 0.1.0 (mounted, not enabled) |
| **Website** | https://github.com/maykinmedia/open-klant |
| **Docs** | https://github.com/maykinmedia/open-klant |

OpenKlant is a customer interaction registry for Dutch municipalities. Part of the Common Ground ecosystem, it tracks customer contacts and interactions linked to cases (zaken). Built by Maykin Media with Django/Python.

**APIs provided:**
- **Klantinteracties API** - Customer interaction tracking
- **Contactmomenten API** - Contact moments registry
- **Klanten API** - Customer registry

```bash
# Create database first
docker exec openregister-postgres psql -U nextcloud -c "CREATE DATABASE openklant;"

# Start OpenKlant
docker-compose --profile openklant up -d

# Enable NC app
docker exec nextcloud php occ app:enable openklant
```

## Common Ground Ecosystem

The following services form the Dutch Common Ground ecosystem for municipal digital infrastructure:

| Service | Purpose | ZGW Compatible | Status |
|---------|---------|:--------------:|--------|
| **Open Register** | Register/schema management | Yes | Enabled |
| **OpenCatalogi** | Software catalog | Yes | Enabled |
| **Valtimo** | BPM/Case management | Yes | Not started |
| **OpenZaak** | ZGW API backend | Yes (reference impl.) | Not started |
| **OpenKlant** | Customer interactions | Yes | Not started |

Start all Common Ground services:
```bash
docker-compose --profile commonground up -d
```

## AI / LLM Services

These services provide AI capabilities. Defined in docker-compose but most require GPU resources.

| Service | Container | Port | Status | Notes |
|---------|-----------|------|--------|-------|
| **Hugging Face TGI** | `openregister-tgi-llm` | 8081 | Not running | Requires GPU, default service |
| **Dolphin VLM** | `openregister-dolphin-vlm` | 8083 | Not running | Requires build + GPU |
| **Presidio** | `openregister-presidio-analyzer` | 5001 | Not running | PII detection, default service |
| **Ollama** (standalone) | `openregister-ollama` | 11434 | Not running | Profile: `ollama` |
| **OpenLLM** | `openregister-openllm` | 3002 | Not running | Profile: `llm-management` |

## Other Services

| Service | Container | Port | Status | Notes |
|---------|-----------|------|--------|-------|
| **Tilburg WOO UI** | `openregister-tilburg-woo-ui` | 3003 | Not running | Default service |
| **Open WebUI** (standalone) | `openregister-open-webui` | 3000 | Not running | Profile: `standalone` |
| **n8n** (standalone) | `openregister-n8n` | 5678 | Not running | Profile: `standalone` |

## Infrastructure

| Service | Container | Port | Status |
|---------|-----------|------|--------|
| **PostgreSQL** (pgvector) | `openregister-postgres` | 5432 | Running, healthy |
| **HaRP** (AppAPI Deploy Daemon) | `openregister-harp` | 8780 | Running |
| **Nextcloud** | `nextcloud` | 8080 | Running |

## Docker Compose Profiles

Start services selectively using profiles:

```bash
# Default (PostgreSQL + Nextcloud + AI services + ExApps)
docker-compose up -d

# With all integrations (OpenProject, XWiki, Open-Xchange, Valtimo, OpenZaak, OpenKlant)
docker-compose --profile integrations up -d

# Individual integrations
docker-compose --profile openproject up -d
docker-compose --profile xwiki up -d
docker-compose --profile ox up -d

# Common Ground services (Valtimo, OpenZaak, OpenKlant)
docker-compose --profile commonground up -d

# Individual Common Ground services
docker-compose --profile valtimo up -d
docker-compose --profile openzaak up -d
docker-compose --profile openklant up -d

# Search backends
docker-compose --profile solr up -d
docker-compose --profile elasticsearch up -d

# Standalone versions (without AppAPI)
docker-compose --profile standalone up -d

# GPU-accelerated LLM
docker-compose --profile ollama up -d

# MariaDB (instead of PostgreSQL)
docker-compose --profile mariadb up -d
```

## Known Issues

1. **XWiki NC app**: Installed but incompatible with Nextcloud 32. XWiki server runs fine on :8086 independently.
2. **Flow/Windmill ExApp**: Container crash-loops due to Windmill OSS limitation - the open source version cannot change default credentials during initialization (`"Not implemented in Windmill's Open Source repository"`).
3. **Open-Xchange**: Docker image requires OX registry credentials and is not publicly pullable.
4. **ExApp health checks**: n8n and Ollama ExApps report unhealthy status - may need health check endpoint investigation.
5. **Open WebUI ExApp**: Container created but not started.
6. **Valtimo**: Requires Keycloak for full authentication. The Docker setup uses a simplified configuration without Keycloak - some features may not work without it.
7. **OpenZaak/OpenKlant**: Require database creation before first start (`CREATE DATABASE openzaak;` / `CREATE DATABASE openklant;`).

## Architecture Overview

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                         Nextcloud 32.0.5 (:8080)                               │
│                                                                                │
│  Custom Apps (enabled):                                                        │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌────────────┐            │
│  │ OpenRegister │ │ OpenCatalogi │ │  NL Design   │ │   MyDash   │            │
│  └──────────────┘ └──────────────┘ └──────────────┘ └────────────┘            │
│  ┌──────────────┐                                                              │
│  │   OpenTalk   │                                                              │
│  └──────────────┘                                                              │
│                                                                                │
│  Custom Apps (not enabled):                                                    │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐                            │
│  │   Valtimo    │ │   OpenZaak   │ │  OpenKlant   │                            │
│  └──────┬───────┘ └──────┬───────┘ └──────┬───────┘                            │
│         │                │                │                                    │
│  App Store Apps:                                                               │
│  ┌──────────────┐ ┌──────────────┐                                             │
│  │ integration_ │ │    xwiki     │                                             │
│  │ openproject  │ │  (disabled)  │                                             │
│  └──────┬───────┘ └──────────────┘                                             │
│         │                                                                      │
│  AppAPI (ExApps via HaRP :8780):                                               │
│  ┌──────┐ ┌──────┐ ┌────────┐ ┌──────────┐                                    │
│  │ n8n  │ │Ollama│ │Open    │ │  Flow    │                                     │
│  │  (!) │ │  (!) │ │WebUI(-)│ │(Windmill)│                                     │
│  └──────┘ └──────┘ └────────┘ │   (X)    │                                     │
│                                └──────────┘                                     │
└────────────────────────────────────────────────────────────────────────────────┘
          │                │                │
          ▼                ▼                ▼
  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
  │  OpenProject │ │    XWiki     │ │Open-Xchange  │
  │  :8085  (OK) │ │  :8086  (OK) │ │  :8087  (N/A)│
  └──────────────┘ └──────────────┘ └──────────────┘

  Common Ground Services:
  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
  │   Valtimo    │ │   OpenZaak   │ │  OpenKlant   │
  │  :8088  (-) │ │  :8089  (-)  │ │  :8090  (-)  │
  └──────────────┘ └──────────────┘ └──────────────┘

Legend: (OK) = healthy, (!) = unhealthy, (-) = not started, (X) = crash-loop, (N/A) = image unavailable
```
