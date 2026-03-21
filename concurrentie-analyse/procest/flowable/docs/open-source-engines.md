# Flowable Open Source Engines

Source: https://www.flowable.com/open-source, https://www.flowable.com/open-source/docs/oss-introduction

## Overview

The Flowable open-source project provides core business process engines that are compact and highly efficient. They are Apache 2.0 licensed, written in Java, and require JDK 17+.

## Three Core Engines

### 1. Process Runtime (BPMN Engine)
- Many years of real-world use
- Fast, efficient, reliable process execution
- Rich Java and REST APIs
- Rich integration capabilities for driving external services
- Supports BPMN 2.0 standard

### 2. Case Runtime (CMMN Engine)
- Same robust, battle-tested architecture as process engine
- Dedicated data model focused on CMMN execution
- Rich REST and Java API
- Optimized for case management workflows
- Supports CMMN 1.1 standard

### 3. Rules Runtime (DMN Engine)
- Similar foundation as other engines
- Dedicated model for optimized DMN execution
- REST and Java API
- Supports DMN 1.1 standard
- Strict mode (default) enforces hit policies per spec
- Custom function delegates for expressions

## Deployment Options

- **Embedded**: Run within a Java application
- **Standalone**: As a service on a server
- **Clustered**: Multi-node deployment
- **Cloud**: Cloud-native deployment
- **Independent**: Each engine runs independently
- **Integrated**: Plug engines into each other (e.g., DMN into BPMN engine)

## Spring Integration

All engines can be integrated with Spring/Spring Boot for a rich business process management suite.

## Database Support

| Database | Driver | Notes |
|----------|--------|-------|
| H2 | Default | Default for development |
| MySQL | mysql-connector-java | Production supported |
| PostgreSQL | postgresql | Production supported |
| Oracle | oracle thin | Production supported |
| MSSQL | Microsoft JDBC / JTDS | Production supported |
| DB2 | db2 | Production supported |

- Uses MyBatis for ORM
- Recommends HikariCP or Tomcat JDBC for connection pooling
- Liquibase for schema management
- JNDI datasource configuration supported

## Architecture

- All database tables prefixed with `ACT_` (e.g., `ACT_DMN_DECISION_TABLE`)
- Deployment cache with LRU support
- SLF4J logging
- Versioning: MAJOR.MINOR.MICRO with source/binary compatibility guarantees
- Experimental features marked with `[EXPERIMENTAL]`
- Internal classes in `.impl.` packages (no stability guarantee)

## Modeling

Free Flowable Cloud Design application available for modeling CMMN, BPMN, DMN and other model types. Register at https://www.flowable.com/account/open-source.

## Community

- GitHub issues: https://github.com/flowable/flowable-engine/issues
- Forum: https://forum.flowable.org
- 9,100+ GitHub stars
- 2,800+ forks
