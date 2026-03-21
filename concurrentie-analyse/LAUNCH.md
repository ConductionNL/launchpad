# Competitor Environment Launch Guide

How to spin up KISS and Dimpact ZAC locally for hands-on comparison with our products (Pipelinq and Procest).

## Prerequisites

The OpenRegister Docker stack must be running with SSO and ZGW services:
```bash
cd openregister
docker compose -f docker-compose.yml -f docker-compose.sso.yml \
  --profile openzaak --profile openklant up -d
```

Verify core services:
```bash
docker ps --format "{{.Names}}\t{{.Status}}" | grep -E "nextcloud|openregister-postgres|keycloak|openzaak|openklant"
```

Expected: `nextcloud`, `openregister-postgres`, `openregister-exapp-keycloak`, `openregister-exapp-openzaak`, `openregister-exapp-openklant` all `Up`.

---

## KISS (Klantinteractie-Servicesysteem)

**What:** Dutch government customer interaction system. Competitor to Pipelinq.
**Tech:** C# ASP.NET Core 8.0 BFF + Vue 3 frontend
**URL:** http://localhost:9030
**Containers:** 3 (kiss-bff, kiss-postgres, kiss-elasticsearch)

### Setup

```bash
# 1. Clone the repository
git clone --depth 1 https://github.com/Klantinteractie-Servicesysteem/KISS-frontend.git /tmp/kiss-setup
cd /tmp/kiss-setup

# 2. Patch the BFF for local OIDC (HTTP Keycloak support)
# In Kiss.Bff/Config/AuthenticationSetup.cs, add:
#   - RequireHttpsMetadata = false (for HTTP OIDC in dev)
#   - OIDC_METADATA_URL env var support (internal Docker hostname for metadata)
#   - OnTokenValidated to map Keycloak roles to .NET ClaimTypes.Role
# (See concurrentie-analyse/pipelinq/kiss/patches/ for the diff)

# 3. Build the Docker image
docker build -t kiss-bff-local:latest .

# 4. Create Keycloak KISS client
KC_TOKEN=$(curl -s -X POST "http://localhost:8081/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=admin&password=admin&grant_type=password&client_id=admin-cli" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

curl -s -X POST "http://localhost:8081/admin/realms/commonground/clients" \
  -H "Authorization: Bearer $KC_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "clientId": "kiss",
    "secret": "kiss-secret-change-me",
    "enabled": true,
    "publicClient": false,
    "directAccessGrantsEnabled": true,
    "redirectUris": ["http://localhost:9030/*"],
    "webOrigins": ["http://localhost:9030"],
    "protocol": "openid-connect"
  }'

# 5. Copy and start our docker-compose
cp /path/to/concurrentie-analyse/pipelinq/kiss/docker-compose.kiss.yml .
docker compose -f docker-compose.kiss.yml up -d
```

Wait ~60 seconds for Elasticsearch to become healthy, then KISS BFF starts.

### Verify

```bash
# Check containers (all 3 should be healthy)
docker ps --format "table {{.Names}}\t{{.Status}}" | grep kiss

# Check UI
curl -s -o /dev/null -w "%{http_code}" http://localhost:9030
# Expected: 200
```

### Access

| Service | URL | Credentials |
|---------|-----|-------------|
| **KISS UI** | http://localhost:9030 | admin / admin (via Keycloak) |
| **Keycloak Admin** | http://localhost:8081/admin | admin / admin |
| **Elasticsearch** | http://localhost:9230 | elastic / kiss-elastic-secret |

### What Works

- Frontend loads (Dutch UI, NL Design themed)
- Keycloak OIDC login flow
- Admin/beheer pages (skills, links, gespreksresultaten, contactverzoeken, nieuws)
- Elasticsearch search backend

### What Needs Additional Config

- BRP/Haal Centraal API (citizen lookup) — needs mock or test API key
- KVK API (company lookup) — needs mock or test API key
- Objects API (afdelingen, groepen, VACs) — can use OpenRegister as Objects API
- KISS-Elastic-Sync (content indexing) — needs Objects API content to sync

### Key Pages to Explore

- **Hoofdpagina** — Main contact registration screen with search
- **Beheer > Skills** — Skill categories for routing
- **Beheer > Links** — Quick links management
- **Beheer > Gespreksresultaten** — Conversation result types
- **Beheer > Contactverzoeken** — Contact request form builder (checkboxes, dropdowns, open fields)
- **Beheer > Nieuws/werkberichten** — News/work messages for agents

### Shutdown

```bash
docker compose -f /tmp/kiss-setup/docker-compose.kiss.yml down
# Clean slate: add -v
```

---

## Dimpact ZAC (Zaakafhandelcomponent)

**What:** Dutch municipal case management system. Competitor to Procest.
**Tech:** Java/WildFly + Angular, Flowable BPMN engine
**URL:** http://localhost:9016
**Containers:** 17 (ZAC + databases + Keycloak + OpenZaak + OpenKlant + Solr + OPA + mocks)
**RAM:** ~8GB free required (16GB recommended)

### Setup

```bash
# 1. Clone the repository
git clone --depth 1 https://github.com/infonl/dimpact-zaakafhandelcomponent.git /tmp/dimpact-zac-setup
cd /tmp/dimpact-zac-setup
```

### 2. Edit docker-compose.yaml

Make these changes directly in `docker-compose.yaml` (do NOT use override files — they merge ports additively):

| Setting | Default | Change to | Reason |
|---------|---------|-----------|--------|
| Keycloak port | `8081:8080` | `8082:8080` | Port 8081 used by openregister-keycloak |
| `KC_HOSTNAME` | `http://localhost:8081` | `http://localhost:8082` | Match new port |
| ZAC port | `8080:8080` | `9016:8080` | Avoid conflict with Nextcloud |
| `CONTEXT_URL` | `http://localhost:8080` | `http://localhost:9016` | Match new port |
| `FEATURE_FLAG_PABC_INTEGRATION` | `true` | `false` | PABC returns 500s without full setup |

### 3. Start (staged — databases first)

```bash
# Step 1: Databases + Redis
docker compose --project-name zac-eval up -d \
  keycloak-database openzaak-database zac-database \
  openklant-database pabc-database redis

# Wait for healthy status (~15s)
sleep 15

# Step 2: Application services
docker compose --project-name zac-eval up -d \
  keycloak openzaak.local solr opa \
  brp-personen-wiremock brp-personen-mock \
  openklant.local office-converter \
  pabc-migrations pabc-api

# Wait for Keycloak + OpenZaak healthy (~60s)
sleep 60

# Step 3: OpenZaak init script (sets up ZAC client credentials)
docker exec zac-eval-openzaak-database-1 \
  bash /docker-entrypoint-initdb.d/database/fill-data-on-startup.sh

# Step 4: Update Keycloak redirect URIs for port 9016
KC_TOKEN=$(curl -s -X POST "http://localhost:8082/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=admin&password=admin&grant_type=password&client_id=admin-cli" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

CLIENT_ID=$(curl -s -H "Authorization: Bearer $KC_TOKEN" \
  "http://localhost:8082/admin/realms/zaakafhandelcomponent/clients?clientId=zaakafhandelcomponent" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)[0]['id'])")

curl -s -X PUT "http://localhost:8082/admin/realms/zaakafhandelcomponent/clients/$CLIENT_ID" \
  -H "Authorization: Bearer $KC_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"redirectUris": ["http://localhost:9016/*", "http://localhost:8080/*"], "webOrigins": ["http://localhost:9016", "http://localhost:8080"]}'

# Step 5: Start ZAC
COMPOSE_PROFILES=zac docker compose --project-name zac-eval up -d zac

# WildFly boot takes ~30-60 seconds
```

### Verify

```bash
# Check containers (should show 16-17 containers)
docker ps --format "table {{.Names}}\t{{.Status}}" | grep zac-eval

# Check ZAC responds (302 = redirect to Keycloak login)
curl -s -o /dev/null -w "%{http_code}" http://localhost:9016
# Expected: 302
```

### Access

| Service | URL | Credentials |
|---------|-----|-------------|
| **ZAC UI** | http://localhost:9016 | testuser1 / testuser1 |
| **Keycloak Admin** | http://localhost:8082 | admin / admin |
| **OpenZaak API** | http://localhost:8001 | — |
| **Solr Admin** | http://localhost:8983 | — |

### Test Users

All follow pattern: **username = password**

| User | Role |
|------|------|
| `testuser1` | Admin (full access) |
| `behandelaar1` | Case handler |
| `coordinator1` | Team coordinator |
| `recordmanager1` | Record manager |
| `raadpleger1` | Viewer (read-only) |
| `functioneelbeheerder1` | Functional admin |

### Key Pages to Explore

- **Dashboard** — Customizable cards with case statistics
- **Zaken** — Case queue with filters
- **Taken** — Task queue
- **Create Case** — Form with case type selection, initiator, description
- **Admin > Beheer parameters** — Case handling configuration per type
- **Admin > Referentietabellen** — Reference data tables
- **Admin > Formulierdefinities** — Form definitions
- **Search** — 4 search modes: cases/tasks/docs, BRP person, KVK company, BAG address

### Shutdown

```bash
cd /tmp/dimpact-zac-setup
COMPOSE_PROFILES=zac docker compose --project-name zac-eval down
# Clean slate (remove all data): add -v
```

---

## Port Summary

| Port | Service | Product |
|------|---------|---------|
| 8080 | Nextcloud | OpenRegister |
| 5432 | PostgreSQL | OpenRegister |
| 8081 | Keycloak | OpenRegister ExApps |
| 9030 | KISS UI | KISS |
| 9230 | KISS Elasticsearch | KISS |
| 9016 | ZAC UI | Dimpact ZAC |
| 8082 | ZAC Keycloak | Dimpact ZAC |
| 8001 | ZAC OpenZaak | Dimpact ZAC |
| 8002 | ZAC OpenKlant | Dimpact ZAC |
| 8983 | ZAC Solr | Dimpact ZAC |
| 8181 | ZAC OPA | Dimpact ZAC |

## Running Everything Together

All three stacks can coexist:
```bash
# OpenRegister (foundation)
cd openregister && docker compose up -d

# KISS (3 containers, ~2GB RAM)
docker compose -f /tmp/kiss-setup/docker-compose.kiss.yml up -d

# ZAC (17 containers, ~8GB RAM)
cd /tmp/dimpact-zac-setup
docker compose --project-name zac-eval up -d
COMPOSE_PROFILES=zac docker compose --project-name zac-eval up -d zac
```

**Total RAM needed:** ~12-16GB for all three stacks running simultaneously.
