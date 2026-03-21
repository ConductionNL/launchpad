---
competitor: espocrm
analyzed_date: 2026-03-14
feature: BPM & Workflow Engine
relevance: high
pipelinq_equivalent: n8n workflow integration, pipeline automation
---

# BPM & Workflow Engine

## Overview

EspoCRM offers two levels of process automation through the **Advanced Pack** extension ($395/year, included in cloud plans):

1. **Workflows** - Simple trigger-action rules for common automations
2. **BPM** - Full BPMN 2.0 visual process designer for complex business logic

Both are no-code/low-code tools accessible from the administration panel.

## Workflows (Simple Automation)

### Trigger Types
- **After record created** - New record event
- **After record updated** - Record modification event (with loop protection)
- **After record created or updated** - Combined trigger
- **Manual** - User-initiated via button on record detail view (configurable label, team visibility)
- **Scheduled** - Cron-based execution against a Report result set
- **Sequential** - Called by another workflow (chaining)
- **Signal** - Triggered by BPM signals (inter-process communication)

### Available Actions
- Send Email (template-based, immediate or delayed)
- Create Record / Create Related Record
- Update Target Record / Update Related Record
- Link/Unlink with another Record
- Apply Assignment Rule (round-robin, least-busy)
- Create Notification
- Make Followed
- Trigger another Workflow
- Run Service Action (custom PHP service)
- Start BPM Process
- Send HTTP Request (external API calls)

### Conditions
- UI condition builder (visual, no-code)
- Formula script conditions (code-based, supports logical operators)

## BPM (Business Process Management)

### Architecture
- Based on BPMN 2.0 standard
- Visual flowchart editor for process design
- Process instances run against specific target records
- One active process per target record + flowchart at a time
- Full execution log with color-coded status visualization

### Flowchart Elements

**Gateways** (decision points):
- Exclusive (XOR) - One path based on conditions
- Parallel (AND) - All paths execute simultaneously
- Inclusive (OR) - One or more paths based on conditions
- Event-based - Wait for external events

**Events** (lifecycle triggers):
- Start events: Timer, Signal, Conditional, Error
- Intermediate events: Timer (delays), Signal, Message, Conditional
- End events: Terminate, Signal, Error, Escalation
- Boundary events: Interrupting and non-interrupting

**Activities** (work units):
- Task - Automated action (same as workflow actions)
- Send Message Task - Email/notification sending
- User Task - Manual work assignment with resolution tracking
- Script Task - Formula execution
- Sub-Process - Embedded or call activity

### Process Lifecycle
1. Created by trigger (automatic, manual, or workflow-initiated)
2. Status: Started > Ended (or Stopped/Interrupted)
3. Visual tracking: Green (done), Yellow (pending), Violet (active), Gray (failed)
4. Manual controls: Stop, reject/interrupt nodes, start from any element, reactivate

### Advanced Features
- **Signals** - Cross-process communication
- **Compensation** - Rollback completed activities on error
- **Drip email campaigns** - Time-delayed email sequences
- **Tracking URLs** - Link click tracking within processes

## Strengths

- Full BPMN 2.0 standard compliance
- Visual drag-and-drop flowchart editor
- Comprehensive event types (timer, signal, conditional, error)
- User Tasks for human-in-the-loop processes
- Process execution logging and visualization
- Manual process manipulation (stop, restart, skip)
- Formula engine integration for complex conditions
- HTTP request action for API integrations

## Weaknesses

- **Paid extension** - Not available in free open-source version ($395/year)
- **No n8n integration** - Custom HTTP requests only, no visual integration builder
- **BPM complexity** - Requires BPMN knowledge; overkill for simple pipeline automations
- **No AI/ML capabilities** - No intelligent routing or prediction
- **No marketplace** - Cannot share or download pre-built process templates
- **Single-instance** - No distributed/multi-node process execution

## Comparison with Pipelinq

| Aspect | EspoCRM BPM | Pipelinq + n8n |
|--------|-------------|----------------|
| Visual editor | BPMN 2.0 flowchart | n8n workflow canvas |
| Complexity | High (full BPM) | Medium (practical automation) |
| Learning curve | Steep (BPMN knowledge needed) | Lower (node-based visual) |
| Cost | $395/year (self-hosted) | Included with Nextcloud |
| Integration breadth | HTTP requests only | 400+ n8n nodes |
| Human tasks | User Tasks with resolution | Nextcloud notifications + forms |
| Process monitoring | Color-coded flowchart | n8n execution history |
| External triggers | Signals, timers, webhooks | Webhooks, schedules, 400+ triggers |
| Template sharing | No | n8n template library |

### Pipelinq Advantages
1. n8n provides broader integration ecosystem (400+ nodes vs HTTP-only)
2. Lower barrier to entry (no BPMN knowledge required)
3. Included in Nextcloud platform (no additional license cost)
4. Real-time collaboration via Nextcloud
5. n8n template library for pre-built automations

### EspoCRM Advantages
1. Tighter CRM integration (BPM operates directly on CRM entities)
2. BPMN 2.0 standard (portable process definitions)
3. Process execution visualization on the flowchart
4. User Tasks with formal resolution tracking
5. Compensation handling for rollback scenarios
