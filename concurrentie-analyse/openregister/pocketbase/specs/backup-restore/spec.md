---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Backup and Restore

## Summary
PocketBase includes built-in backup and restore functionality accessible from the admin dashboard and API. Backups capture the entire SQLite database and can be stored locally or on S3.

## Key Features
- One-click backup creation from admin UI
- Backup upload and restore
- Scheduled automatic backups via Backups options
- S3 storage support for backups
- Backup file download
- Cron-based backup scheduling
- Complete database snapshot (SQLite file copy)

## Architecture
- `apis/backup.go` - Backup API endpoints
- `apis/backup_create.go` - Backup creation logic
- `apis/backup_upload.go` - Backup upload handler
- `core/base_backup.go` - Core backup/restore implementation

## Relevance to OpenRegister
OpenRegister relies on Nextcloud's backup mechanisms and database-level backups. PocketBase's integrated backup UI with S3 support and scheduling is more self-contained and user-friendly for standalone deployments.
