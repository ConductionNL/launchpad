# Audit Logging

## Summary

Open Product implements comprehensive audit logging for both admin and API operations. Every CRUD action is recorded with the acting user, event type, and object data. Logs are pruned monthly via Celery.

## Implementation

### TimelineLogProxy (proxy of django-timeline-logger's TimelineLog)
- `content_object` -- GenericForeignKey to the audited object
- `extra_data` -- JSON metadata containing:
  - `event` -- create/read/update/delete/download
  - `acting_user` -- {identifier, display_name}
  - `object_data` -- serialized object state (for create/update/delete)
  - `remarks` -- description of the action
  - `_cached_object_repr` -- cached string representation for performance

### Event Types (Events enum)
- `create`, `read`, `update`, `delete`, `download`

### Audit Sources
1. **Admin** -- `audit_admin_create/read/update/delete` (Django user as actor)
2. **API** -- `audit_api_create/read/update/delete/download` (user_id/display as actor)
3. **Automation** -- `audit_automation_update` (actor: "Automation", used for date-triggered status changes)

### ViewSet Integration
`AuditTrailViewSetMixin` automatically logs API operations on both ProductType and Product viewsets.

## Log Retention
- Celery beat task `prune_logs` runs monthly (1st of each month at midnight)
- Configurable retention: `PRUNE_LOGS_TASK_KEEP_DAYS` (default: 30 days)

## Already in OpenRegister
- Nextcloud activity log for object changes
- Basic audit trail in Nextcloud

## Not yet in OpenRegister
- **Structured audit events** with typed event categories
- **User snapshot in logs** (survives user deletion)
- **Automation attribution** for system-triggered changes
- **Object data snapshot** at time of change
- **Configurable log pruning** via Celery scheduled task
- **Admin and API audit separation**
