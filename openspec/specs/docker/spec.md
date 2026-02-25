# Docker Development Environment

## Purpose
Defines the standard Docker development environment for all projects in this workspace.

## Requirements

### Requirement: Primary Compose File
The OpenRegister docker-compose MUST be the primary development environment.

#### Scenario: Developer starts working
- GIVEN a developer wants to work on any app
- WHEN they start the environment
- THEN they MUST use `docker compose -f openregister/docker-compose.yml up -d`
- AND all apps MUST be volume-mounted into the Nextcloud container

### Requirement: Port Mapping
Standard port mappings MUST be maintained across environments.

#### Scenario: Services are running
- GIVEN the Docker environment is started
- THEN Nextcloud MUST be available on host port 8080
- AND PostgreSQL MUST be available on host port 5432
- AND frontend UIs SHOULD use ports 3000-3099

### Requirement: File Permissions
Volume-mounted app files MUST be accessible for both the container and host.

#### Scenario: Developer edits a file on host
- GIVEN an app file is volume-mounted
- WHEN the developer edits it on the host
- THEN the change MUST be reflected inside the container
- AND if permissions prevent editing, `docker exec -u root nextcloud chown -R 1000:1000 /path/` MUST fix it

### Requirement: OPcache Clearing
PHP changes MUST be reflected after clearing OPcache.

#### Scenario: Developer changes a PHP file
- GIVEN a PHP file was modified
- WHEN the developer wants to see the change
- THEN running `docker exec nextcloud apache2ctl graceful` MUST clear OPcache
- AND the new code MUST take effect on the next request

### Requirement: Environment Reset
A clean environment reset MUST be available.

#### Scenario: Environment is corrupted
- GIVEN the development environment is in a bad state
- WHEN the developer runs `bash clean-env.sh` or `/clean-env`
- THEN all containers MUST be stopped and removed
- AND all volumes MUST be deleted
- AND the environment MUST be rebuilt from scratch
- AND core apps MUST be installed (openregister, opencatalogi, softwarecatalog, nldesign, mydash)
