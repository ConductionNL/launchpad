---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Integrations

## Overview

NocoDB supports connecting to external databases, importing data from various sources, and integrating with notification services. The integration system is evolving from a plugin-based App Store to a more structured integration architecture.

## Database Connections

### Supported Databases
- **MySQL** — Full support with MysqlUi adapter
- **PostgreSQL** — Full support with PgUi adapter
- **SQLite** — Full support with SqliteUi adapter (default for OSS)
- **SQL Server** — Via MSSQL client
- **Oracle** — Via Oracle client
- **Snowflake** — Via Snowflake adapter
- **Databricks** — Via Databricks adapter

### Connection Model
Each database connection is a "Source" within a base:
- Multiple sources per base
- Connection parameters: host, port, database, username, password
- SSL/TLS configuration
- Connection pooling via Knex

### Data Source Management
- UI: Base Settings > Data Sources tab
- Add new data source with database type selection
- Visibility toggle per source
- Schema sync (detect external database changes)

## Import Sources

### Airtable Import
- `SyncService` handles Airtable base import
- Maps Airtable field types to NocoDB UITypes
- Preserves relations, views, and data
- Sync logs track import progress

### File Import
- CSV import
- Excel import
- JSON import

## App Store Plugins (Deprecated)

The App Store provides notification integration plugins:
- **Slack** — Send notifications to Slack channels
- **Microsoft Teams** — Send notifications to Teams
- **Discord** — Send notifications to Discord
- **Whatsapp Twilio** — Send SMS via Twilio
- **Twilio** — Send SMS/voice via Twilio
- **Mattermost** — Send notifications to Mattermost

Note: App Store is being deprecated. Email & Storage plugins moved to Account/Setup. Remaining plugins moving to integrations.

## AI Integrations

### Integration Store
- `IntegrationsType.Ai` — AI provider integration
- Token tracking: input_tokens, output_tokens, total_tokens, model
- Slot-based storage for integration-specific data

### AI Features
- **AI Button** — Column type that triggers AI actions
- **AI Text** — LongText with AI prompt generation
- **Chat** — Chat sessions with AI (ChatMessage, ChatSession models)

## Storage Configuration

Configurable file storage backends:
- Local filesystem
- S3-compatible storage
- Other cloud storage providers
- Configured via Account > Setup > Configure Storage

## Relevance to OpenRegister

1. **Multi-database support** is NocoDB's killer feature for legacy integration
2. **Airtable import** enables easy migration from competitor
3. OpenRegister could benefit from CSV/Excel import capabilities
4. **AI integration** is built into the type system (AI columns)
5. OpenRegister's Nextcloud integration provides file storage natively
6. Plugin deprecation shows the challenge of maintaining integration ecosystems
