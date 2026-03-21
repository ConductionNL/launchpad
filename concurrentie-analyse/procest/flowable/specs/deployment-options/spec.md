---
competitor: flowable
analyzed_date: 2026-03-14
feature: Deployment Options
category: technical
---

# Deployment Options

## What It Is

Flowable supports multiple deployment models from embedded Java library to cloud-hosted SaaS, with Docker and Kubernetes support.

## Open Source Deployment

### Embedded
- Add Flowable JARs to Java application classpath
- Configure via `flowable.cfg.xml` or Spring beans
- Engine lifecycle managed by application
- Suitable for integration into existing Java apps

### Standalone
- Deploy as WAR file to Tomcat/Jetty
- Spring Boot application with embedded server
- REST API exposed automatically
- Requires JDK 17+

### Docker
- Official Docker images available
- JRE 21 base image (updated Aug 2025)
- Docker Compose for multi-service setup

### Kubernetes
- Helm charts available at https://github.com/flowable/helm
- Cluster deployment support
- Horizontal scaling capabilities

## Commercial Deployment

### Customer Managed
- Deploy on-premise or own cloud
- Full control over infrastructure
- Customer responsible for operations
- HA and scalability supported

### Flowable Cloud Shared
- Multi-tenant cloud hosting
- Managed by Flowable
- Shared infrastructure
- Lower cost entry point

### Flowable Cloud Dedicated
- Single-tenant cloud hosting
- Managed by Flowable
- Dedicated infrastructure
- Maximum isolation and control

## Technical Requirements

### Runtime
- JDK 17+ (JRE 21 recommended for Docker)
- Servlet container (Tomcat, Jetty) or Spring Boot
- Supported databases (H2, MySQL, PostgreSQL, Oracle, MSSQL, DB2)

### Database Configuration
- JDBC connection configuration
- Connection pooling (HikariCP recommended)
- JNDI datasource support
- Schema auto-migration via Liquibase
- Multi-database support in single installation

### Spring Boot Integration
- Spring Boot starters for each engine
- Auto-configuration
- Spring-managed transactions
- Actuator integration for monitoring

## Relevance to Procest

### Key Differences
- Flowable requires JVM stack; Procest runs on PHP/Nextcloud
- Flowable needs separate database; Procest uses Nextcloud's database
- Flowable deployment is complex (multiple services); Procest is a Nextcloud app (one-click install)

### Procest Advantages
- **Simpler deployment**: Nextcloud app store installation
- **No Java dependency**: PHP runtime only
- **Shared infrastructure**: Uses existing Nextcloud server
- **Lower operational cost**: No separate database/server
- **Docker**: Already part of Nextcloud docker-compose stack

### Applicable Patterns
- Docker-based deployment for consistent environments
- Kubernetes support for production scaling
- HA configuration patterns
- Database migration strategy (Liquibase-like approach)
