# Celery Automation

## Summary

Open Product uses Celery with Redis broker and django-celery-beat for scheduled tasks. Two recurring tasks maintain product state consistency and log hygiene.

## Scheduled Tasks

### 1. Update Product Statuses
- **Task**: `openproduct.producten.tasks.set_product_states`
- **Schedule**: Daily at midnight (crontab: minute=0, hour=0)
- **Logic**: Iterates all products, calls `save()` on any where `check_start_datum()` or `check_eind_datum()` returns True
- **Effect**: Products with reached start_datum transition to ACTIEF; products with reached eind_datum transition to VERLOPEN
- **Audit**: Each transition creates an automation audit log entry

### 2. Prune Timeline Logs
- **Task**: `openproduct.logging.tasks.prune_logs`
- **Schedule**: Monthly on the 1st at midnight
- **Args**: `PRUNE_LOGS_TASK_KEEP_DAYS` (default: 30)
- **Effect**: Deletes audit log entries older than the retention period

## Configuration
- `CELERY_BROKER_URL` -- Redis URL (default: `redis://localhost:6379`)
- `CELERY_BEAT_SCHEDULER` -- `django_celery_beat.schedulers:DatabaseScheduler`
- Schedules stored in database (manageable via admin)

## Already in OpenRegister
- n8n workflows for scheduled/triggered automation
- Cron-like scheduling via n8n

## Not yet in OpenRegister
- **Date-driven status automation** running as scheduled background tasks
- **Log rotation/pruning** as a managed background job
- **Database-stored schedules** (editable via admin without code changes)
