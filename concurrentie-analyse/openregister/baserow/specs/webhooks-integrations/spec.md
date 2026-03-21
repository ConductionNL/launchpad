---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Webhooks and Integrations

## Summary

Baserow supports table-level webhooks for event-driven automation, plus a growing set of built-in integrations (SMTP, Slack, AI providers, LocalBaserow). The automation system (a separate module) provides workflow-based automations with triggers and action nodes.

## Webhooks

### Model
Located at `backend/src/baserow/contrib/database/webhooks/models.py`

- **TableWebhook**: Attached to a specific table
  - `url`: Target URL (up to 2000 chars)
  - `request_method`: POST, GET, PUT, PATCH, DELETE
  - `active`: Auto-deactivated after repeated failures
  - `include_all_events`: Listen to all or specific events
  - `use_user_field_names`: Use field names vs field IDs in payload
  - `failed_triggers`: Failure counter

- **TableWebhookEvent**: Specific events a webhook listens to
  - Events: `rows.created`, `rows.updated`, `rows.deleted`
  - View-level events for row enter/leave

- **TableWebhookHeader**: Custom HTTP headers for requests

- **TableWebhookCall**: Call history/audit log
  - Stores request/response details for debugging

### Webhook Payload
- Contains changed row data
- Optionally uses user field names for readability
- Includes event type, table ID, and timestamp

### Auto-deactivation
After repeated failures, webhooks are automatically deactivated to prevent continuous retry loops.

## Integrations

Located at `backend/src/baserow/contrib/integrations/`

### Integration Types

1. **LocalBaserow** (`LocalBaserowIntegrationType`)
   - Connect to tables within the same Baserow instance
   - Used by Application Builder data sources
   - Supports row CRUD, list, filter, sort

2. **SMTP** (`SMTPIntegrationType`)
   - Email sending via SMTP
   - Used by automation nodes for email notifications

3. **Slack** (`SlackBotIntegrationType`)
   - Send messages to Slack channels
   - Used in automation and builder workflow actions

4. **AI** (`AIIntegrationType`)
   - Connect to AI providers
   - Supports: OpenAI, Anthropic, Mistral, Ollama, OpenRouter
   - Used by AI fields and AI automation nodes

### Data Sync Sources (Enterprise)

External data sync connectors that import data into Baserow tables:

- **PostgreSQL** (`PostgreSQLDataSyncType`) - Sync from external PostgreSQL databases
- **iCal Calendar** (`ICalCalendarDataSyncType`) - Import calendar events
- **GitHub Issues** (`GitHubIssuesDataSyncType`) - Sync GitHub issues
- **GitLab Issues** (`GitLabIssuesDataSyncType`) - Sync GitLab issues
- **Jira Issues** (`JiraIssuesDataSyncType`) - Sync Jira issues
- **HubSpot Contacts** (`HubspotContactsDataSyncType`) - Sync CRM contacts
- **Local Baserow Table** (`LocalBaserowTableDataSyncType`) - Sync between tables

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Webhooks | Per-table, multi-event, auto-deactivation | N/A (uses n8n for automation) |
| Integrations | SMTP, Slack, AI, LocalBaserow | n8n workflows, MCP |
| Data sync | 7+ external sources (enterprise) | Source-based data import |
| AI integration | Built-in AI fields + automation nodes | N/A |
| Event system | Row create/update/delete, view enter/leave | Nextcloud events |
