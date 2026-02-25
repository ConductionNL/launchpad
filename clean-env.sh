#!/bin/bash
# clean-env.sh — Reset the OpenRegister development environment
# Stops all containers, removes volumes, brings everything back up,
# and installs the core apps.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/openregister/docker-compose.yml"
CONTAINER_NAME="nextcloud"
APPS_TO_INSTALL=(openregister opencatalogi softwarecatalog nldesign mydash docudesk)

echo "=== Clean Environment Reset ==="
echo "Using docker-compose: $COMPOSE_FILE"
echo ""

# Step 1: Shut down all containers and remove volumes
echo "[1/6] Stopping containers and removing volumes..."
docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>&1 || true

# Also remove any lingering containers with openregister- prefix or the nextcloud container
# (may exist from other compose projects sharing the same container names)
echo "  Cleaning up any conflicting containers..."
docker ps -aq --filter "name=openregister-" | xargs -r docker rm -f 2>/dev/null || true
docker rm -f "$CONTAINER_NAME" 2>/dev/null || true

# Remove the network if it's still around
docker network rm openregister-network 2>/dev/null || true
echo "Done."
echo ""

# Step 2: Bring containers back up
echo "[2/6] Starting containers..."
# Start core services first (db + nextcloud), then try the rest.
# GPU-dependent services (tgi-llm, exapp-ollama, etc.) may fail without NVIDIA drivers — that's OK.
docker compose -f "$COMPOSE_FILE" up -d db 2>&1
echo "  Database started, starting Nextcloud..."
docker compose -f "$COMPOSE_FILE" up -d nextcloud 2>&1
echo "  Starting remaining services (GPU failures are OK)..."
docker compose -f "$COMPOSE_FILE" up -d 2>&1 || true
echo "Done."
echo ""

# Step 3: Wait for Nextcloud to be ready
echo "[3/6] Waiting for Nextcloud to be ready..."
MAX_WAIT=300
WAITED=0
until docker exec "$CONTAINER_NAME" php occ status --output=json 2>/dev/null | grep -q '"installed":true'; do
    if [ $WAITED -ge $MAX_WAIT ]; then
        echo "ERROR: Nextcloud did not become ready within ${MAX_WAIT}s"
        exit 1
    fi
    sleep 5
    WAITED=$((WAITED + 5))
    echo "  Waiting... (${WAITED}s)"
done
echo "Nextcloud is ready."
echo ""

# Step 4: Configure trusted domains
echo "[4/6] Configuring trusted domains..."
TRUSTED_DOMAINS=("localhost:3000" "localhost:8080" "localhost:3030")
for i in "${!TRUSTED_DOMAINS[@]}"; do
    docker exec "$CONTAINER_NAME" php occ config:system:set trusted_domains $((i + 1)) --value="${TRUSTED_DOMAINS[$i]}"
done
echo "Done."
echo ""

# Step 5: Configure Presidio for DocuDesk entity recognition
echo "[5/6] Configuring Presidio endpoint..."
docker exec "$CONTAINER_NAME" php occ config:app:set openregister fileManagement --value='{"vectorizationEnabled":false,"provider":null,"chunkingStrategy":"RECURSIVE_CHARACTER","chunkSize":1000,"chunkOverlap":200,"enabledFileTypes":["txt","md","html","json","xml","csv","pdf","docx","doc","xlsx","xls"],"ocrEnabled":false,"maxFileSizeMB":100,"extractionScope":"objects","textExtractor":"llphant","extractionMode":"background","maxFileSize":100,"batchSize":10,"dolphinApiEndpoint":"","dolphinApiKey":"","presidioApiEndpoint":"http://openregister-presidio-analyzer:3000","entityRecognitionEnabled":true,"entityRecognitionMethod":"presidio"}'
docker exec "$CONTAINER_NAME" php occ config:system:set docudesk_presidio_analyzer_url --value='http://openregister-presidio-analyzer:3000/analyze'
docker exec "$CONTAINER_NAME" php occ config:system:set docudesk_presidio_anonymizer_url --value='http://openregister-presidio-analyzer:3000/anonymize'
echo "Done."
echo ""

# Step 6: Install apps
echo "[6/6] Installing apps..."
for APP in "${APPS_TO_INSTALL[@]}"; do
    echo "  Enabling $APP..."
    docker exec "$CONTAINER_NAME" php occ app:enable "$APP" || {
        echo "  WARNING: Failed to enable $APP (may not be available yet)"
    }
done
echo ""

echo "=== Environment ready ==="
echo "Nextcloud: http://localhost:8080"
echo "UI: http://localhost:3000"
echo "Login: admin / admin"
echo "Trusted domains: localhost, ${TRUSTED_DOMAINS[*]}"
echo "Installed apps: ${APPS_TO_INSTALL[*]}"
