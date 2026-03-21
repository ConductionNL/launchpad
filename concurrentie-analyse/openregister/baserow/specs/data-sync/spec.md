---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Data Sync

## Summary

Baserow's data sync feature allows tables to be populated from external data sources and kept in sync. The open-source version supports PostgreSQL and iCal sources. Enterprise adds GitHub, GitLab, Jira, HubSpot, and cross-table sync.

## Architecture

Located at `backend/src/baserow/contrib/database/data_sync/`

```
data_sync/
  handler.py                    # Sync orchestration
  registries.py                 # DataSyncType registry
  models.py                     # DataSync, DataSyncSyncedProperty models
  data_sync_types.py            # Base sync type
  postgresql_data_sync_type.py  # PostgreSQL connector
  ical_data_sync_type.py        # iCal connector
  job_types.py                  # Background sync jobs
  tasks.py                      # Celery tasks for periodic sync
```

## Open Source Data Sync Types

### PostgreSQL Data Sync
- Connect to external PostgreSQL databases
- Map columns to Baserow fields
- Periodic sync support
- Connection configuration: host, port, database, username, password, table/query

### iCal Calendar Data Sync
- Import events from iCal/ICS feeds
- Maps event properties to Baserow fields
- URL-based feed configuration

## Enterprise Data Sync Types

Located at `enterprise/backend/src/baserow_enterprise/data_sync/`

### GitHub Issues
- Sync issues from GitHub repositories
- Maps: title, body, state, labels, assignees, dates
- API token authentication

### GitLab Issues
- Sync issues from GitLab projects
- Similar mapping to GitHub

### Jira Issues
- Sync issues from Jira projects
- Maps Jira fields to Baserow columns

### HubSpot Contacts
- Sync contacts from HubSpot CRM
- Maps contact properties

### Local Baserow Table
- Sync data between Baserow tables
- Cross-database data sharing

## Sync Mechanism

1. Configure data sync with source credentials and field mapping
2. Initial sync creates rows from source data
3. Periodic sync (configurable interval) checks for changes
4. Synced fields are read-only (source of truth is external)
5. Additional non-synced fields can be added for local annotations

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| External sync | 7 source types | Source-based import (manual) |
| PostgreSQL | Native connector | N/A |
| Calendar | iCal sync | N/A |
| Issue trackers | GitHub, GitLab, Jira | N/A |
| CRM | HubSpot | N/A |
| Periodic sync | Celery beat scheduling | N/A |
| Read-only sync | Synced fields are read-only | N/A |
