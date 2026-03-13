# OpenZaak Installation and Deployment

## Deployment Options

1. **Kubernetes** — for public testing and production environments
2. **Docker Compose** — for private testing on local machines
3. **Python code** — for development purposes

## Hardware Requirements

### Municipality Sizing

| Inhabitants | Expected Concurrent Users |
|------------|--------------------------|
| 10,000 | 100 |
| 50,000 | 250 |
| 100,000 | 500 |
| 500,000 | 1,000 |
| 1,000,000 | 2,000 |

### Performance Translation

| Concurrent Users | Requests/Second |
|-----------------|----------------|
| 100 | 15 |
| 250 | 30 |
| 500 | 60 |
| 1,000 | 120 |
| 2,000 | 235 |

### Minimum System Requirements

- Platform: 64-bit
- Processor: 4-16 CPUs at 2.0 GHz
- RAM: 8-32 GB
- Disk: 20 GB (excluding document storage)

| Requests/Second | CPUs | Memory (GB) |
|----------------|------|-------------|
| 25 | 4 | 8 |
| 50 | 6 | 12 |
| 100 | 12 | 24 |
| 150 | 14 | 28 |
| 200 | 16 | 32 |

### PostgreSQL Requirements

Database performance is critical. Minimum TPS (transactions per second) via pgbench:

| Max Cases/Day | Min TPS |
|--------------|---------|
| 100 | 200 |
| 1,000 | 500 |
| 10,000 | 1,000 |
| >10,000 | 1,500 |

### Kubernetes Recommendations
- 2+ load balancer replicas (Traefik)
- As many replicas as available CPUs (minus LB and other services)

## Prerequisites

- PostgreSQL 14+ with PostGIS 3.2+
- Redis for caching and Celery
- SMTP server for email (optional)
- DNS entries for Open Zaak and Open Notificaties
- For NLX: publicly available domain name

## Key Environment Variables

### Required
- `SECRET_KEY` — cryptographic secret
- `ALLOWED_HOSTS` — comma-separated domains
- `CACHE_DEFAULT` — Redis address (default: localhost:6379/0)
- `CACHE_AXES` — Redis for brute-force protection
- `EMAIL_HOST` — SMTP server

### Database
- `DB_NAME` (default: openzaak)
- `DB_USER` (default: openzaak)
- `DB_PASSWORD` (default: openzaak)
- `DB_HOST` (default: db for Docker, localhost otherwise)
- `DB_PORT` (default: 5432)
- `DB_CONN_MAX_AGE` (default: 60)
- `DB_POOL_ENABLED` (experimental, default: False)

### Application
- `OPENZAAK_DOMAIN` — canonical domain (e.g., openzaak.example.com:8443)
- `IS_HTTPS` — defaults to inverse of DEBUG
- `MIN_UPLOAD_SIZE` — max POST body size (default: 4GiB)
- `NOTIFICATIONS_DISABLED` — disable notifications (default: False)
- `JWT_EXPIRY` — JWT validity in seconds (default: 3600)
- `ZAAK_IDENTIFICATIE_GENERATOR` — use-creation-year or use-start-datum-year

### Document Storage
- `DOCUMENTEN_API_BACKEND` — filesystem, azure_blob_storage, or s3_storage
- Azure: `AZURE_ACCOUNT_NAME`, `AZURE_CLIENT_ID`, `AZURE_TENANT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_CONTAINER`
- S3: `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_STORAGE_BUCKET_NAME`, `S3_ENDPOINT_URL`

### Performance
- `FUZZY_PAGINATION` — approximate counts for performance (default: False)
- `FUZZY_PAGINATION_COUNT_LIMIT` — exact count threshold (default: 500)
- `UWSGI_HTTP_TIMEOUT` — request timeout (default: 60s)

### Experimental
- `ENABLE_CLOUD_EVENTS` — enable cloud events (default: False)
- `ZAAK_EIGENSCHAP_WAARDE_VALIDATION` — validate ZaakEigenschap values against Eigenschap.specificatie

### Superuser Provisioning
- `OPENZAAK_SUPERUSER_USERNAME` — creates superuser on startup if set
- `OPENZAAK_SUPERUSER_EMAIL` — superuser email (default: admin@admin.org)
- `DJANGO_SUPERUSER_PASSWORD` — superuser password

## Document Import Configuration
- `IMPORT_DOCUMENTEN_BASE_DIR` — base directory for bulk imports
- `IMPORT_DOCUMENTEN_BATCH_SIZE` — rows per batch (default: 500)
- `IMPORT_RETENTION_DAYS` — days before import cleanup (default: 7)

## Post-Install Checklist
1. Configure Sites framework with correct domain
2. Set up Notificaties component configuration
3. Register webhook subscriptions
4. Create admin users and groups
5. Configure OIDC if needed
6. Set up API applications and authorizations
7. Create/import catalogi
