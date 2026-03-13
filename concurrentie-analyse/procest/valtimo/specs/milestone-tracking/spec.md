---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Milestone Tracking -- Valtimo

## Purpose
Tracks process progression through predefined checkpoints linked to BPMN flow nodes. Milestones provide a business-friendly view of "how far along" a case is, abstracting away the technical complexity of BPMN process flow into meaningful progress indicators for case workers and managers.

## Architecture Overview
- **Backend module**: `milestones/` (Kotlin, Spring Boot)
- **Frontend module**: `milestone/` Angular library
- **Integration**: Milestones mapped to BPMN flow nodes (activities, gateways, events)
- **Grouping**: Milestones organized into sets for logical grouping

## Data Model

### Milestone
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique milestone ID |
| title | String | Human-readable milestone name |
| processDefinitionKey | String | Associated BPMN process definition |
| flowNodeId | String | BPMN element ID this milestone tracks |
| color | String | Display color for visual indicators |
| order | Integer | Display ordering |

### MilestoneSet
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique set ID |
| title | String | Set display name |
| caseDefinitionName | String | Associated case type |
| milestones | List | Ordered list of milestones in this set |

### MilestoneInstance (runtime)
| Field | Type | Description |
|-------|------|-------------|
| milestoneId | UUID | Reference to milestone definition |
| processInstanceId | String | Active process instance |
| reached | Boolean | Whether the flow node has been reached |
| reachedOn | LocalDateTime | When the milestone was reached |

## Business Logic

### Milestone Evaluation
1. BPMN process instance executes, passing through flow nodes
2. Operaton engine fires execution events for each flow node
3. Milestone module listens for flow node events
4. When a flow node matching a milestone is reached, the milestone instance is marked as `reached`
5. Progress visible in case detail view as a milestone timeline

### Milestone Sets
- Group related milestones into logical progression sets
- Example set: "Intake" -> "Assessment" -> "Decision" -> "Closure"
- Multiple sets possible per case type (e.g., main process + sub-process milestones)
- Sets displayed as progress bars or checklists in the UI

### Configuration
- Admin maps BPMN flow nodes to named milestones via management UI
- Milestones can be auto-deployed from configuration files
- Color and ordering configurable per milestone

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Case status tracking via status field (single current state)
- Pipeline views show cases in status columns (Kanban-style)
- No multi-milestone progress tracking tied to process execution
- Deadline tracking provides temporal progress awareness

### Valtimo advantages
- Multi-milestone progress tracking (not just current status)
- Direct mapping to BPMN flow nodes (automatically tracked)
- Milestone sets for grouped progress visualization
- Color-coded visual timeline
- Historical tracking of when each milestone was reached

### Valtimo disadvantages
- Requires BPMN process for milestone tracking (no manual milestone completion)
- Milestones only track "reached" -- no partial progress within a milestone
- Tightly coupled to process execution -- non-process cases cannot use milestones
- No SLA/deadline integration with milestones
