# ArkCase Docker/Local Setup Investigation

## Date: 2026-03-14

## Executive Summary

ArkCase **cannot be run locally in a reasonable timeframe** on a shared development machine. It requires a dedicated Kubernetes cluster with ~16GB RAM and 12 pods. We attempted a full Helm-based deployment on K3d which partially succeeded before running out of memory on a 24GB system already running other containers.

---

## 1. Deployment Options Investigated

### Option A: Vagrant VM (arkcase-ce repo)
- **Repo**: https://github.com/ArkCase/arkcase-ce
- **Requirements**: VirtualBox + Vagrant, 16GB+ RAM, 50GB+ disk
- **Status**: Pre-built Vagrant box available at `arkcase/arkcase-ce:3.3.1-r1-a`
- **How**: Create `Vagrantfile`, run `vagrant up`
- **Pros**: Single command, bundles everything
- **Cons**: Requires VirtualBox (not available in WSL2), very old version (3.3.1)

### Option B: Kubernetes + Helm Charts (ATTEMPTED)
- **Repo**: https://github.com/ArkCase/ark_helm_charts
- **Chart**: `arkcase/app` v0.9.17 (appVersion 25.09.00)
- **Status**: Partially deployed, ran out of memory
- **Details**: See Section 3 below

### Option C: Individual Docker Images
- **Registry**: `public.ecr.aws/arkcase/`
- **Status**: Images are publicly accessible, but no docker-compose.yml exists
- **The core image is EMPTY** -- `/app/tomcat/webapps/` has no WAR files. The deployer init container must download and deploy artifacts from a separate artifacts container.

### Option D: Build from Source
- **Repo**: https://github.com/ArkCase/ArkCase
- **Requirements**: Java 8, Maven 3.5+, Node.js, Spring Cloud Config Server
- **Status**: Requires a running Vagrant VM with all infrastructure services already running
- **Build command**: `mvn -DskipITs clean install`

---

## 2. Architecture: 12 Required Pods/Services

From the Helm chart (`arkcase/app` Chart.yaml), ArkCase requires these services:

| Pod | Image | Purpose | Port |
|-----|-------|---------|------|
| `arkcase-acme-0` | `arkcase/step-ca` | TLS certificate authority (ACME) | -- |
| `arkcase-app-artifacts` | `arkcase/artifacts-*` | WAR file + config artifact server | 443 (HTTPS) |
| `arkcase-app-proxy-0` | HAProxy | Reverse proxy / load balancer | 8443 |
| `arkcase-content-main-0` | Alfresco + MinIO | Content/document storage (S3 mode by default) | -- |
| `arkcase-core-0` | `arkcase/core:3.0.0` | ArkCase Java app (Tomcat) | 8443 |
| `arkcase-ldap-0` | `arkcase/samba` | Active Directory / LDAP | -- |
| `arkcase-messaging-0` | `arkcase/artemis` | Message queue (ActiveMQ Artemis) | 61613, 61616 |
| `arkcase-rdbms-0` | `arkcase/postgres:13` or `arkcase/mariadb:10.6` | Database | -- |
| `arkcase-reports-0` | `arkcase/pentaho-ce` | Pentaho reporting | 8443 |
| `arkcase-reports-cron-0` | `arkcase/pentaho-ce` | Pentaho scheduled jobs | -- |
| `arkcase-search-0` | `arkcase/solr` | Apache Solr search | 8983 |
| `arkcase-zookeeper-0` | Apache ZooKeeper | Coordination service | -- |

Additionally, each pod has 1-4 init containers:
- **deployer** (`arkcase/deployer`): Downloads and installs artifacts
- **setperm** (`arkcase/setperm`): Sets file permissions
- **init-dependencies** (`arkcase/nettest`): Waits for dependencies to be ready
- **init-dbinit**: Database schema initialization

### Core image analysis
- **Size**: 2.25 GB
- **Base**: Custom ArkCase base image with FIPS-compliant BouncyCastle crypto
- **Entrypoint**: `/entrypoint` -> `run-developer /usr/local/bin/tomcat`
- **Config Server**: Requires Spring Cloud Config Server on `https://localhost:9999`
- **Key env vars**: `BASE_DIR=/app`, `CATALINA_BASE=/app/tomcat`, `CONFIG_URL`, `NODE_ID`
- **Important**: The tomcat webapps directory is EMPTY in the image. The deployer init container downloads the WAR from the artifacts container and deploys it.

---

## 3. Helm Deployment Attempt (2026-03-14)

### Setup Steps
```bash
# Install k3d and helm
curl -s https://raw.githubusercontent.com/k3d-io/k3d/main/install.sh | K3D_INSTALL_DIR=~/bin USE_SUDO=false bash
curl -fsSL https://get.helm.sh/helm-v3.17.3-linux-amd64.tar.gz | tar xz -C /tmp && mv /tmp/linux-amd64/helm ~/bin/helm

# Create k3d cluster
k3d cluster create arkcase --port "9017:443@loadbalancer" --agents 0 --servers 1 --k3s-arg "--disable=traefik@server:0"

# Add Helm repo
helm repo add arkcase https://arkcase.github.io/ark_helm_charts

# Install hostpath provisioner (for PVCs)
helm install --create-namespace --namespace hostpath-provisioner hostpath-provisioner arkcase/hostpath-provisioner

# Deploy ArkCase
helm install arkcase arkcase/app --create-namespace --namespace arkcase --set global.persistence.storageClassName=hostpath --timeout 10m
```

### Results
- Helm install succeeded (chart deployed to Kubernetes)
- All 12 pods created and started pulling images
- After ~5 minutes: acme, proxy, content-main, rdbms, zookeeper reached Running state
- After ~10 minutes: messaging, search started coming up
- After ~15 minutes: Memory exhaustion (21GB/23GB used, 1.3GB available)
- Pods started CrashLoopBackOff and ImagePullBackOff
- Core pod stuck in Init:2/4 (waiting for search/Solr at tcp://search:8983)

### Resource Requirements
- **Minimum RAM**: 16GB dedicated (no other containers)
- **Recommended RAM**: 24GB+ for comfortable operation
- **Disk**: ~15GB for Docker images alone (core image is 2.25GB)
- **Image pull time**: 6+ minutes for deployer image alone
- **Full startup time**: Estimated 15-30 minutes from `helm install` to functional app

### Cleanup
```bash
k3d cluster delete arkcase
docker rmi public.ecr.aws/arkcase/core:3.0.0 public.ecr.aws/arkcase/samba:latest public.ecr.aws/arkcase/postgres:13 public.ecr.aws/arkcase/solr:latest public.ecr.aws/arkcase/artemis:latest
```

---

## 4. Docker Images Available (public.ecr.aws/arkcase/)

Verified publicly accessible images:
- `arkcase/core:3.0.0` -- 2.25 GB (ArkCase core, Java/Tomcat)
- `arkcase/postgres:13` -- PostgreSQL database
- `arkcase/samba:latest` -- Samba/LDAP
- `arkcase/solr:latest` -- Apache Solr search
- `arkcase/artemis:latest` -- ActiveMQ Artemis messaging
- `arkcase/deployer:latest` -- 935 MB (artifact deployment)
- `arkcase/setperm:latest` -- 270 MB (permission setup)
- `arkcase/nettest:latest` -- 398 MB (dependency checker)
- `arkcase/pentaho-ce:*` -- Pentaho Community Edition
- `arkcase/neo4j:*` -- Neo4J (analytics, disabled by default)

### Image pull issues
- Some images failed with `toomanyrequests: Rate exceeded` (ECR rate limiting)
- The `aws-arkcase-pull` image pull secret warning appeared but didn't block community images

---

## 5. Why a Minimal Docker-Compose Is Not Feasible

1. **No standalone mode**: The core image has no WAR files. It relies on the deployer container to download artifacts from the artifacts container. This is a Kubernetes-specific init container pattern.

2. **TLS everywhere**: All inter-service communication uses TLS with certificates issued by the step-ca ACME pod. Without this CA infrastructure, services can't communicate.

3. **Config server dependency**: The tomcat startup script polls `https://localhost:9999/actuator/health` waiting for the Spring Cloud Config Server. Without it, the app won't start (90-second timeout then fail).

4. **Complex init chain**: Core requires: ACME (TLS certs) -> Config Server -> Deployer (WAR download) -> Permission setup -> Dependency check (LDAP, DB, Search, Messaging all must be ready)

5. **Spring Cloud Config**: Configuration is not baked into images. A config server must serve the configuration, and the `.arkcase` config directory must be mounted.

---

## 6. Recommended Approach for Future Evaluation

### If you need to run ArkCase locally:

1. **Dedicated machine**: Use a machine with 24GB+ RAM, no other Docker containers
2. **K3d approach**: Follow the steps in Section 3 above
3. **Wait time**: Budget 30+ minutes for all pods to come up
4. **Access**: `https://localhost:9017/arkcase` (with self-signed cert warning)
5. **Login**: `arkcase-admin@arkcase.org` / `@rKc@3e`

### Alternative: Request a Demo
ArkCase offers scheduled demos via HubSpot:
- https://meetings.hubspot.com/john-sung1/clone

---

## 7. GitHub Repository Structure

| Repository | Purpose |
|-----------|---------|
| `ArkCase/ArkCase` | Main source code (Java, 66.4%) |
| `ArkCase/.arkcase` | Configuration files |
| `ArkCase/arkcase-ce` | Vagrant VM (Packer + Ansible) |
| `ArkCase/ark_helm_charts` | Kubernetes Helm charts |
| `ArkCase/DevOps` | CloudFormation, Packer, Docker helpers |
| `ArkCase/acm-config-server` | Spring Cloud Config Server |
| `ArkCase/ark_arkcase_core` | Core Docker image build |
| `ArkCase/ark_solr` | Solr Docker image build |
| `ArkCase/ark_samba` | Samba/LDAP Docker image build |
| `ArkCase/ark_artemis` | Artemis messaging Docker image build |
| `ArkCase/ark_postgres` | PostgreSQL Docker image build |
| `ArkCase/ark_pentaho_ce` | Pentaho CE Docker image build |
| `ArkCase/ark_deployer` | Deployer Docker image build |

Total: 84+ repositories in the ArkCase GitHub organization.
