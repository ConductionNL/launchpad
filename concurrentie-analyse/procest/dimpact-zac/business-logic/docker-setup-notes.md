# Dimpact ZAC Docker Setup Notes

## Repository
- **Source:** https://github.com/infonl/dimpact-zaakafhandelcomponent
- **Clone location:** `/tmp/dimpact-zac-setup/`
- **Version tested:** 4.4.38 (build main, 13-03-2026)
- **Last verified:** 2026-03-14

## Architecture Overview

Dimpact ZAC (Zaakafhandelcomponent) is a Dutch municipal case management system built on:
- **Backend:** Java/WildFly (bootable JAR), Flowable BPMN engine
- **Frontend:** Angular (Material Design)
- **Authentication:** Keycloak (OIDC), realm "zaakafhandelcomponent"
- **ZGW Backend:** OpenZaak (Zaakgericht Werken API)
- **Search:** Apache Solr
- **Authorization:** OPA (Open Policy Agent) + PABC (new IAM system)
- **Database:** PostgreSQL (separate databases for ZAC, Keycloak, OpenZaak, OpenKlant, PABC)

## Minimum Required Services (17 containers)

There is NO truly minimal setup -- ZAC requires all 16 "default" profile services plus ZAC itself.

### Default profile services (always started):
1. **keycloak-database** - PostgreSQL for Keycloak
2. **keycloak** - OIDC identity provider (port 8082)
3. **openzaak-database** - PostgreSQL for OpenZaak (PostGIS)
4. **openzaak.local** - ZGW APIs (Zaken, Documenten, Catalogi, Besluiten, Autorisaties)
5. **redis** - Cache for OpenZaak
6. **solr** - Full-text search engine with 'zac' core
7. **opa** - Open Policy Agent (authorization)
8. **brp-personen-wiremock** - BRP (Basisregistratie Personen) mock
9. **brp-personen-mock** - BRP Haal Centraal API mock
10. **openklant-database** - PostgreSQL for OpenKlant
11. **openklant.local** - OpenKlant API (klantinteracties)
12. **office-converter** - LibreOffice document converter
13. **pabc-database** - PostgreSQL for PABC
14. **pabc-migrations** - PABC database migrations (init container)
15. **pabc-api** - PABC authorization API
16. **zac-database** - PostgreSQL for ZAC

### ZAC service (profile: "zac"):
17. **zac** - The application itself (WildFly, port 8080 internally)

### Optional profiles (NOT required for basic operation):
- `metrics` - OpenTelemetry, Jaeger, Prometheus, Grafana
- `itest` - Integration test dependencies (GreenMail, BagStub, etc.)
- `objecten` - Objecten/Objecttypen APIs (for productaanvraag flow)
- `opennotificaties` - Notification service
- `openarchiefbeheer` - Archive management

## Resource Requirements

- **RAM:** At minimum 8GB free (16GB recommended). ZAC alone has a 4GB memory limit.
- **CPU:** Multi-core recommended; WildFly boot takes 30-50 seconds with adequate resources, but can time out (>300s) under memory pressure.
- **Disk:** ~5GB for Docker images

## Key Configuration Changes Made (in docker-compose.yaml directly)

### Port mappings (to avoid conflicts with openregister stack):
- Keycloak: `8082:8080` (port 8081 taken by openregister-keycloak)
- Keycloak health: `19001:9000` (was 9001, conflicted)
- ZAC: `9016:8080` (custom port, avoids conflict with nextcloud on 8080)
- ZAC management: `19990:9990`
- Other services keep defaults: OpenZaak `8001`, OpenKlant `8002`, Solr `8983`, OPA `8181`, office-converter `8083`

### Environment variables (edited directly in docker-compose.yaml):
- `CONTEXT_URL=http://localhost:9016` (ZAC's public URL)
- `KC_HOSTNAME=http://localhost:8082` (Keycloak hostname, must be reachable from both browser and containers)
- `FEATURE_FLAG_PABC_INTEGRATION=false` (disable new IAM; PABC API returns 500s without proper setup)

### Keycloak client configuration:
- Added `http://localhost:9016/*` to the `zaakafhandelcomponent` client's redirect URIs via Keycloak Admin API
- This must be done AFTER Keycloak is healthy, BEFORE accessing ZAC

### Important: Do NOT use docker-compose.override.yaml for port changes
Override files merge ports lists (additive), causing duplicate port bindings. Edit docker-compose.yaml directly instead.

## Startup Procedure

### Step 0: Clone the repository
```bash
git clone --depth 1 https://github.com/infonl/dimpact-zaakafhandelcomponent.git /tmp/dimpact-zac-setup
```
Then edit docker-compose.yaml: change Keycloak port to 8082, KC_HOSTNAME to http://localhost:8082, ZAC port to 9016, CONTEXT_URL to http://localhost:9016, FEATURE_FLAG_PABC_INTEGRATION to false.

### Step 1: Start databases first
```bash
cd /tmp/dimpact-zac-setup
docker compose --project-name zac-eval up -d keycloak-database openzaak-database zac-database openklant-database pabc-database redis
```
Wait for all databases to show `(healthy)` status.

### Step 2: Start application services
```bash
docker compose --project-name zac-eval up -d keycloak openzaak.local solr opa brp-personen-wiremock brp-personen-mock openklant.local office-converter pabc-migrations pabc-api
```
Wait for Keycloak and OpenZaak to show `(healthy)` status (~60 seconds).

### Step 3: Run OpenZaak database init scripts
The init scripts that set up ZAC client credentials in OpenZaak may not auto-run:
```bash
docker exec zac-eval-openzaak-database-1 bash /docker-entrypoint-initdb.d/database/fill-data-on-startup.sh
```

### Step 4: Update Keycloak redirect URIs for port 9016
```bash
# Get admin token
KC_TOKEN=$(curl -s -X POST "http://localhost:8082/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=admin&password=admin&grant_type=password&client_id=admin-cli" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

# Get client UUID
CLIENT_ID=$(curl -s -H "Authorization: Bearer $KC_TOKEN" \
  "http://localhost:8082/admin/realms/zaakafhandelcomponent/clients?clientId=zaakafhandelcomponent" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)[0]['id'])")

# Update redirect URIs
curl -s -X PUT "http://localhost:8082/admin/realms/zaakafhandelcomponent/clients/$CLIENT_ID" \
  -H "Authorization: Bearer $KC_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"redirectUris": ["http://localhost:9016/*", "http://localhost:8080/*"], "webOrigins": ["http://localhost:9016", "http://localhost:8080"]}'
```

### Step 5: Start ZAC
```bash
COMPOSE_PROFILES=zac docker compose --project-name zac-eval up -d zac
```
WildFly boot takes ~20-60 seconds. ZAC responds with HTTP 302 when ready.

### Step 6: Verify
- ZAC UI: http://localhost:9016 (redirects to Keycloak login)
- Keycloak admin: http://localhost:8082 (admin/admin)
- Login with: testuser1/testuser1

## Shutdown / Cleanup
```bash
cd /tmp/dimpact-zac-setup
COMPOSE_PROFILES=zac docker compose --project-name zac-eval down
# To also remove volumes (clean slate):
# COMPOSE_PROFILES=zac docker compose --project-name zac-eval down -v
```

## 1Password CLI

The `start-docker-compose.sh` script uses 1Password CLI (`op run --env-file="./.env.tpl"`) to inject secrets. This is **NOT required** for local development -- all secrets have sensible defaults in `docker-compose.yaml`. The 1Password references are only used for:
- BAG API key
- KVK API key
- Municipality email address
- SMTP credentials

## Test User Credentials

All test users follow the pattern: **username = password**.

### Old IAM users (FEATURE_FLAG_PABC_INTEGRATION=false):
- `testuser1` / `testuser1` (admin-level, display: "Test User1 Special Characters")
- `testuser2` / `testuser2`
- `functioneelbeheerder1` / `functioneelbeheerder1` (functional admin)
- `recordmanager1` / `recordmanager1`
- `coordinator1` / `coordinator1`
- `behandelaar1` / `behandelaar1`
- `raadpleger1` / `raadpleger1`

### New IAM users (FEATURE_FLAG_PABC_INTEGRATION=true):
- `beheerder1newiam` / `beheerder1newiam` (admin for all domains)
- `behandelaar1newiam` / `behandelaar1newiam` (handler domain 1)
- `coordinator1newiam` / `coordinator1newiam` (coordinator domain 1)
- `recordmanager1newiam` / `recordmanager1newiam`
- `raadpleger1newiam` / `raadpleger1newiam` (viewer domain 1)

## Coexistence with OpenRegister Stack

The following openregister-network services run alongside ZAC without conflicts:
- `nextcloud` on port 8080
- `openregister-postgres` on port 5432
- `openregister-keycloak` on port 8081
- `openregister-openzaak` on port 8089
- `openregister-openklant` on port 8090
- `openregister-redis` on port 6379

ZAC uses its own isolated network (`zac-eval_default`) with its own Redis, databases, and services. No ports conflict when using the configuration above.

## Known Issues

1. **PABC 500 errors:** The PABC API returns 500 when ZAC requests application roles. Must set `FEATURE_FLAG_PABC_INTEGRATION=false` unless PABC is properly configured.
2. **OpenZaak init scripts timing:** The fill-data-on-startup.sh runs in background and may miss its window. Manual execution required after first start.
3. **Memory pressure:** Running alongside other Docker stacks can cause OOM kills (exit 137) and WildFly boot timeouts. Need ~14GB free RAM for both stacks.
4. **Keycloak redirect_uri:** Default config only allows port 8080. Must update client URIs when using custom ports (Step 4 above).
5. **Port conflicts with override files:** Docker Compose override files merge port lists additively. Edit docker-compose.yaml directly to change ports.
6. **Case type config errors:** Fresh install shows "Case handling parameters not (fully) configured" for all case types - this is expected and requires admin setup of BRP Audit Logging parameters per case type.
