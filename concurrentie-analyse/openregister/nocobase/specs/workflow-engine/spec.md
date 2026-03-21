---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Workflow Engine

## Purpose

NocoBase includes a built-in workflow engine for automating business processes. It supports visual workflow design with triggers, conditions, data operations, and human interaction nodes. The engine is split across the core `plugin-workflow` and 20+ extension plugins for additional node types.

## Architecture Overview

```
Trigger (Collection change / Schedule / Action)
    |
    v
Dispatcher (event queue, deduplication)
    |
    v
Processor (walks node linked-list)
    |
    v
Instructions (Query, Condition, HTTP, JS, Manual, etc.)
    |
    v
Jobs (execution results per node)
```

### Core Components

- **Plugin** (`plugin-workflow/src/server/Plugin.ts`) - Registers triggers, instructions, functions. Manages workflow cache and execution lifecycle.
- **Processor** (`Processor.ts`) - Walks the doubly-linked node list, executes instructions, manages job state and transactions.
- **Dispatcher** (`Dispatcher.ts`) - Event queue with deduplication, handles concurrent trigger events.
- **Trigger** (base class) - Abstract trigger that listens for events and initiates executions.
- **Instruction** (base class) - Abstract node that performs work and returns a job result.

## Data Model

### Workflows Collection
- `title` - Workflow name
- `key` - Unique key (supports versioning - only one `current` per key)
- `type` - Trigger type identifier
- `triggerConfig` - Trigger-specific configuration (JSON)
- `enabled` - Active status
- `current` - Whether this is the current version
- `executed` - Execution counter

### Flow Nodes Collection
- `title` - Node name
- `type` - Instruction type identifier
- `config` - Instruction-specific configuration (JSON)
- `upstreamId` / `downstreamId` - Linked list pointers
- `branchIndex` - For conditional branches

### Executions Collection
- `workflowId` - Parent workflow
- `context` - Trigger context data
- `status` - STARTED / RESOLVED / FAILED / ERROR / ABORTED / CANCELED / REJECTED / RETRY_NEEDED

### Jobs Collection
- `executionId` - Parent execution
- `nodeId` - Which node produced this job
- `status` - PENDING / RESOLVED / FAILED / ERROR / ABORTED / CANCELED / REJECTED / RETRY_NEEDED
- `result` - Job output data

## Business Logic

### Triggers (4 types)

1. **Collection Trigger** - Fires on collection record create/update/destroy. Supports condition filters and changed-field detection. Uses Sequelize model hooks (`afterCreateWithAssociations`, `afterUpdateWithAssociations`, `afterDestroy`).

2. **Schedule Trigger** - Cron-based scheduling. Supports both global schedules and per-record schedules (e.g., "3 days after createdAt").

3. **Action Trigger** (`plugin-workflow-action-trigger`) - Fires before or after API actions. Can intercept and modify responses.

4. **Custom Action Trigger** (`plugin-workflow-custom-action-trigger`) - User-initiated triggers attached to UI buttons.

### Instructions (20+ types)

**Data Operations:**
- `QueryInstruction` - Query collection records
- `CreateInstruction` - Create records
- `UpdateInstruction` - Update records
- `DestroyInstruction` - Delete records
- `AggregateInstruction` - COUNT, SUM, AVG, MIN, MAX

**Logic:**
- `ConditionInstruction` - If/else branching
- `MultiConditionsInstruction` - Switch/case with multiple branches
- `CalculationInstruction` - Expression evaluation
- `DynamicCalculation` - Formula evaluation with collection field references
- `DateCalculationInstruction` - Date arithmetic

**Flow Control:**
- `EndInstruction` - Terminate workflow
- `OutputInstruction` - Set workflow output
- `DelayInstruction` - Wait for duration
- `LoopInstruction` (`plugin-workflow-loop`) - Iterate over arrays
- `ParallelInstruction` (`plugin-workflow-parallel`) - Execute branches concurrently

**Integration:**
- `RequestInstruction` (`plugin-workflow-request`) - HTTP requests
- `SQLInstruction` (`plugin-workflow-sql`) - Raw SQL execution
- `ScriptInstruction` (`plugin-workflow-javascript`) - Sandboxed JavaScript (VM)
- `JSONQueryInstruction` (`plugin-workflow-json-query`) - JMESPath queries
- `VariableMappingInstruction` (`plugin-workflow-json-variable-mapping`) - Data transformation

**Human Interaction:**
- `ManualInstruction` (`plugin-workflow-manual`) - Human approval/review tasks
- `CCInstruction` (`plugin-workflow-cc`) - Carbon copy notifications
- `NotificationInstruction` (`plugin-workflow-notification`) - Send notifications

### Execution Model

The Processor uses a linked-list traversal:
1. Start from the first node (no upstream)
2. Execute instruction, receive Job with status
3. If RESOLVED, move to downstream node
4. If PENDING (e.g., manual approval), pause execution
5. If FAILED/ERROR, mark execution as failed
6. Branch nodes create parallel paths via `branchIndex`

Snowflake IDs used for distributed execution tracking. LRU cache for per-workflow loggers.

## Requirements

### Functional
- Visual workflow designer with drag-and-drop nodes
- Multiple trigger types (data, schedule, action, manual)
- Branching, looping, parallel execution
- Variable passing between nodes
- Human approval/review steps
- Workflow versioning (new version replaces old, executions complete on old version)

### Non-functional
- Execution persistence (survive restarts)
- Concurrent execution handling
- Error recovery and retry
- Execution logging per workflow

## Comparison Notes

### vs n8n (OpenRegister's workflow engine)
- NocoBase workflows are tightly integrated with the data model (collection triggers)
- n8n is a standalone workflow engine with broader integration support (400+ nodes)
- NocoBase has built-in human approval nodes; n8n requires external tools
- n8n has a more mature visual designer
- NocoBase workflows stored in same database; n8n runs as separate service
- Both support JavaScript execution, HTTP requests, and conditional logic
