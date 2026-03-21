# ArkCase Deployment and Installation Guide

**Source:** GitHub README, arkcase.com/prerequisites/, arkcase.com/overview/

## Prerequisites

### Hardware Requirements (Minimum)
- 2 CPU cores
- 16 GB RAM
- 50 GB disk space (Vagrant VM alone is 11GB)

Production sizing is beyond the default guide scope due to environment differences.

### Software Requirements

| Component | Version | Notes |
|---|---|---|
| Java | Java 8 (AdoptOpenJDK) | Only Java 8 supported; not tested on 9/10/11 |
| Maven | 3.5+ | Build tool |
| Node.js | 6 (macOS), 8+ (Windows/Linux) | UI assembly at deployment time |
| npm | Comes with Node.js | Package manager |
| Yarn | Latest | Package manager |
| Git | Latest | Used by Angular build tools |
| VirtualBox | Latest | For Vagrant VM |
| Vagrant | Latest | For Vagrant VM |
| Tomcat | 9 | Application server |

### Operating System
- Developed and tested on **Windows 10** and **CentOS 7.x**
- Linux installation guide tested on **Fedora 34 and 35**
- In theory, any OS with Java 8 JDK works

### Hostname Restrictions
- Oracle does not work on hosts with dashes in hostname
- ArkCase webapp does not work on hosts with dashes or underscores in hostname

## Deployment Options

### Option 1: Pre-Built Virtual Machine (Evaluation)
- Download pre-built ArkCase VM from https://github.com/ArkCase/arkcase-ce
- No developer tools needed
- Contains all services pre-configured

### Option 2: Developer Setup (Full Build)

#### Step 1: Build the Vagrant VM
The Vagrant VM hosts all required services:
- Apache Solr
- Apache ActiveMQ
- MySQL
- Alfresco Content Services
- Pentaho

Build instructions at: https://github.com/ArkCase/arkcase-ce

After VM is up, verify these URLs:
- `https://arkcase-ce.local/solr` -- Solr admin
- `https://arkcase-ce.local/share` -- Alfresco Share
- `https://arkcase-ce.local/pentaho` -- Pentaho
- `https://arkcase-ce.local/VirtualViewerJavaHTML5` -- expect 503

#### Step 2: Clone and Build ArkCase
```bash
git clone https://github.com/ArkCase/ArkCase.git
cd ArkCase
mvn -DskipITs clean install
```
Produces: `acm-standard-applications/arkcase/target/arkcase-(version).war`

#### Step 3: Clone Configuration
Configuration in separate repository: https://github.com/ArkCase/.arkcase

#### Step 4: Start Configuration Server
```bash
# Download from https://github.com/ArkCase/acm-config-server/releases
java -jar config-server-0.0.1.jar
# Custom port:
java -Dserver.port=8888 -jar config-server-0.0.1.jar
```
Default port: 9999

#### Step 5: Configure Tomcat

**Enable Native Connector:**
- macOS: `brew install tomcat-native`
- Windows/Linux: download from https://tomcat.apache.org/download-native.cgi

**TLS Configuration (server.xml):**
- Add HTTPS connector on port 8843
- TLS 1.2 with certificate chain from `.arkcase/acm/private/`
- Enable APR connector

**Environment (bin/setenv.sh):**
```bash
export JAVA_OPTS="-Djava.net.preferIPv4Stack=true -Duser.timezone=GMT \
  -Djavax.net.ssl.keyStorePassword=password \
  -Djavax.net.ssl.trustStorePassword=password \
  -Djavax.net.ssl.keyStore=${user.home}/.arkcase/acm/private/arkcase.ks \
  -Djavax.net.ssl.trustStore=${user.home}/.arkcase/acm/private/arkcase.ts \
  -Dspring.profiles.active=ldap \
  -Dacm.configurationserver.propertyfile=${user.home}/.arkcase/acm/conf.yml \
  -Xms1024M -Xmx1024M"
export NODE_ENV=development
export CATALINA_PID=$CATALINA_HOME/temp/catalina.pid
```

#### Step 6: Deploy WAR
1. Copy `arkcase-(version).war` to `$TOMCAT_HOME`
2. Rename to `arkcase.war`
3. Move to `$TOMCAT_HOME/webapps`
4. First startup takes 5-10 minutes

#### Step 7: Trust Self-Signed Certificate
ArkCase uses self-signed TLS certificates. Trust the ArkCase CA certificate in your OS/browser.

#### Step 8: Login
- URL: `https://arkcase-ce.local/arkcase`
- Default admin: `arkcase-admin@arkcase.org` / `@rKc@3e`

### Option 3: Kubernetes (Enterprise, v25.09.00+)
- Clustering support for Kubernetes environments added in release 25.09.00
- Details not publicly documented

## Installation Architecture

The full ArkCase installation includes these separately installed components:

1. **Database** (MySQL/PostgreSQL/Oracle/SQL Server)
2. **ECM Repository** (Alfresco Content Services, CMIS 1.1)
3. **Solr** (Apache Solr)
4. **Frevvo** (Forms server)
5. **Pentaho** (Reporting)
6. **Snowbound / PDFTron** (Document viewer)
7. **ArkCase Webapp** (Java WAR on Tomcat)
8. **Spring Cloud Config Server** (Configuration)

Each component runs as a separate process. The ECM repository also uses its own database (can be the same server but separate database).

## IDE Support
- **IntelliJ IDEA** -- full support
- **Eclipse** -- full support
- **Visual Studio Code** -- code editing only, manual deployment required

## Configuration

Configuration is stored in the `.arkcase` folder structure:
- `acm/private/` -- TLS certificates, keystores, truststores
- `acm/conf.yml` -- Configuration server connection
- UI labels, connection strings, RBAC config, business rules, business process definitions

Configuration server: Spring Cloud Config Server provides centralized configuration management.

## Additional Documentation Resources
- Confluence wiki: https://arkcase.atlassian.net/wiki/spaces/AD/
  - ArkCase on Linux
  - ArkCase on Windows
- arkcase.com/developer-support/new-installation-guidelines/
- arkcase.com/prerequisites/
