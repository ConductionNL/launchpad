---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Audit Logs

## Purpose

NocoBase's `plugin-audit-logs` automatically records data changes (create, update, destroy) across all collections, providing a complete audit trail of who changed what and when.

## Architecture Overview

The audit log system uses Sequelize model hooks to intercept data operations:

```
Data Operation (create/update/destroy)
    |
    v
Model Hook (afterCreate, afterUpdate, afterDestroy)
    |
    v
Audit Log Record (who, what, when, changes)
    |
    v
auditLogs collection (database)
```

## Data Model

### Audit Logs Collection
- `createdAt` - Timestamp of the operation
- `type` - Operation type (create, update, destroy)
- `collectionName` - Which collection was affected
- `recordId` - ID of the affected record
- `userId` - Who performed the operation
- `changes` - JSON diff of changed fields (old value -> new value)

### Audit Changes Collection
- `field` - Field name that changed
- `before` - Previous value
- `after` - New value

## Business Logic

### Hook Registration
The plugin registers `afterCreate`, `afterUpdate`, and `afterDestroy` hooks on all collections that opt-in to audit logging.

### Change Detection
On update operations:
1. Compare old and new field values
2. Record only changed fields
3. Store both before and after values
4. Handle relation changes (association IDs)

### Querying Audit Logs
- Filter by collection, user, date range, operation type
- View change details (field-level diffs)
- Pagination for large audit trails

## Requirements

### Functional
- Automatic logging of all CRUD operations
- Field-level change tracking (before/after values)
- User attribution (who made the change)
- Timestamp recording
- Filter and search audit logs

### Non-functional
- Minimal performance impact on data operations
- Audit log storage management (archival/purging)
- Tamper-resistant log storage

## Comparison Notes

### vs OpenRegister
- OpenRegister has object-level versioning but not field-level change tracking
- NocoBase audit logs are automatic; OpenRegister versioning is explicit
- NocoBase tracks the user who made changes; OpenRegister tracks version metadata
- Neither has built-in log archival or retention policies
- Both store audit data in the same database as application data
