# EspoCRM BPM & Workflow Documentation

**Source:** https://docs.espocrm.com/administration/bpm/ and https://docs.espocrm.com/administration/workflows/
**Fetched:** 2026-03-14
**Extension:** Advanced Pack ($395/year, included in cloud plans)

## Workflows (Simple Automation)

The Workflows tool automates simple business processes using trigger-action rules. Available in the Advanced Pack extension.

### Trigger Types

1. **After record created** - Fires when a new record is created
2. **After record updated** - Fires when an existing record is updated (loop protection built-in)
3. **After record created or updated** - Combines both triggers
4. **Manual** - User-triggered via button on record detail view; configurable label and visibility teams
5. **Scheduled** - Runs on a cron-like schedule; uses a Report to determine which records to process
6. **Sequential** - Called by another workflow; enables complex chained logic
7. **Signal** - Triggered by BPM signals (inter-process communication)

### Conditions

- **UI condition builder** - Visual condition configuration
- **Formula conditions** - Script-based conditions with logical operators

### Actions Available

- **Send Email** - Using email templates; recipients from target, related records, users, or explicit addresses; immediate or delayed
- **Create Record** - Create any entity type; optionally link to target; formula for field calculation
- **Create Related Record** - Create record related to target
- **Update Target Record** - Modify specific fields; formula support; supports adding/removing from arrays
- **Update Related Record** - Modify fields on related records
- **Link with another Record** - Add relationships (e.g., add team)
- **Unlink from another Record** - Remove relationships
- **Apply Assignment Rule** - Auto-assign using round-robin or least-busy
- **Create Notification** - Notify specific users with message (supports placeholders)
- **Make Followed** - Force users to follow a record
- **Trigger another Workflow** - Chain workflows; can target related records
- **Run Service Action** - Execute custom service class methods
- **Start BPM Process** - Launch a BPM process from workflow
- **Send HTTP Request** - Call external APIs (webhooks out)

## BPM (Business Process Management)

The BPM tool provides no-code/low-code process modeling and automation using BPMN 2.0 standard.

### Key Differences from Workflows

- Workflows: simple trigger-action rules, no visual flow
- BPM: complex logic with diverging/converging flows, delays, user interactions, visual flowchart editor, process execution log

### Process Flowcharts

- Available under Administration > Flowcharts
- Each flowchart targets a specific entity type (Target Type)
- Can be activated/deactivated
- Admin-only creation; regular users can view (with role permissions)

### Processes

- A Process is an instance of a Flowchart running against a specific target record
- Statuses: Started, Ended
- Only one process per target record + flowchart can be active simultaneously

**Starting a process:**
1. Automatically - by conditions, signals, or scheduling defined in the flowchart
2. Manually - via "Start Process" button on record detail view
3. By Workflow rule - using "Start BPM Process" action

**Execution visualization:**
- Green = processed
- Yellow = pending
- Violet = in process
- Gray = failed

**Process manipulation:**
- Stop manually (edit access required)
- Reject or interrupt specific flow nodes
- Start flow from any element (mid-process recovery)
- Reactivate ended/stopped processes

### Flowchart Elements

1. **Gateways** (yellow diamonds) - Diverge/converge flows
   - Exclusive gateway (XOR)
   - Parallel gateway (AND)
   - Inclusive gateway (OR)
   - Event-based gateway

2. **Events** (circles) - Start, end, and interrupt process flow
   - Start events (timer, signal, conditional, error)
   - Intermediate events (timer, signal, message, conditional)
   - End events (terminate, signal, error, escalation)
   - Boundary events (interrupting/non-interrupting)

3. **Activities** (gray rectangles)
   - Task (automated action)
   - Send Message Task
   - User Task (manual work assignment)
   - Script Task (formula execution)
   - Sub-Process (embedded or call activity)

4. **Flows**
   - Sequence Flow (solid arrow) - execution order
   - Conditional flows with formula expressions

### Conditions

Can check:
- Target record fields
- Related records (many-to-one, parent relationships)
- Records created by the Process via tasks
- User Task resolution
- Formula expressions (e.g., `status == 'New' && assignedUserId == null`)

### Advanced BPM Features

- **Signals** - Inter-process communication mechanism
- **Compensation** - Undo completed activities on error
- **BPM-specific formula functions** - Process-aware calculations
- **Drip email campaigns** - Time-delayed email sequences using BPM
- **Tracking URLs** - Link click tracking within BPM processes

## Relevance to Pipelinq

EspoCRM's BPM engine is a direct competitor to Pipelinq's pipeline automation. Key observations:

1. **Strengths:** Full BPMN 2.0 visual editor, comprehensive event types, process execution logging
2. **Weakness:** Only available in paid Advanced Pack ($395/year)
3. **Pipelinq advantage:** Native integration with n8n for workflow automation (more flexible, code-free)
4. **Pipelinq advantage:** Pipeline-specific UX vs generic BPM (which requires BPM knowledge)
