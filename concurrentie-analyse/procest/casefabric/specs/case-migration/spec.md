---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Live Case Definition Migration
category: core
---

# Live Case Definition Migration

## Overview

CaseFabric supports migrating running case instances from one definition version to another without stopping or losing state. This is critical for long-running cases (months/years) where the case model evolves.

## Implementation Details

### Migration Command

`MigrateDefinition` command triggers migration on a running case:
- API: `POST /cases/{id}/migration`
- Accepts new definition reference
- Engine loads the new definition and applies it to the running case

### Migration Process

The `Case.migrateCaseDefinition()` method:

1. **Update definition reference** -- `setDefinition(newDefinition)`
2. **Migrate team** -- `getCaseTeam().migrateDefinition(newTeamModel)`
   - Preserve existing members
   - Remove roles no longer in definition
   - Add new roles
3. **Migrate case file** -- `getCaseFile().migrateDefinition(newFileModel)`
   - Preserve matching items
   - Drop items not in new definition (`CaseFileItemDropped` event)
   - Migrate matching items (`CaseFileItemMigrated` event)
   - Disconnect dropped items from sentry network
4. **Migrate case plan** -- `getCasePlan().migrateDefinition(newPlanModel)`
   - Match existing plan items to new definition by name
   - Preserve state of matching items
   - Drop items not in new definition (`PlanItemDropped` event)
   - Migrate matching items (`PlanItemMigrated` event)
   - Human tasks get `HumanTaskMigrated` (new performer role, task model)
   - Dropped human tasks get `HumanTaskDropped`
   - Disconnect dropped plan items from sentry network

### Events Generated

| Event | Description |
|-------|-------------|
| `CaseDefinitionMigrated` | Case definition updated |
| `PlanItemMigrated` | Plan item matched to new definition |
| `PlanItemDropped` | Plan item not found in new definition |
| `CaseFileItemMigrated` | Case file item matched to new definition |
| `CaseFileItemDropped` | Case file item not found in new definition |
| `HumanTaskMigrated` | Human task updated (role, model) |
| `HumanTaskDropped` | Human task no longer in definition |

### Safety Mechanisms

- Migration is an event-sourced operation (can be replayed)
- Sentry network disconnects removed items to prevent stale criteria
- `PlanItem.migrateItemDefinition()` preserves previous definition reference for comparison
- `WorkflowTask.migrateDefinition()` checks for changes in name, performer role, and task model before emitting migration event

### API Route

`CaseMigrationRoute` handles:
- `POST /cases/{id}/migration` -- triggers migration
- Returns `MigrationStartedResponse`

## Relevance for Procest

Live migration of running cases is highly valuable for government processes that span months or years. Consider:

1. **Definition versioning** -- track which version each case is running
2. **Graceful migration** -- update definitions without losing case state
3. **Drop/preserve logic** -- handle removed and added fields/tasks
4. **Audit trail** -- record what changed during migration
5. **Sentry cleanup** -- ensure reactive criteria stay consistent after changes
