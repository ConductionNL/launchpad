---
competitor: flowable
analyzed_date: 2026-03-14
feature: CMMN Case Management
category: core-engine
---

# CMMN Case Management

## What It Is

Flowable's CMMN (Case Management Model and Notation) engine provides dynamic case management for complex, unpredictable workflows. It is Flowable's primary differentiator - they build everything around the case as the foundational concept, not the process.

## Key Capabilities

### Case Modeling (CMMN 1.1)
- Case plan model with stages
- Human tasks (plan items)
- Process tasks (linking to BPMN processes)
- Decision tasks (linking to DMN decisions)
- Milestones for progress tracking
- Sentries (entry/exit criteria) for event-driven logic
- Discretionary items for ad-hoc tasks
- Repetition rules
- Required/manual activation rules

### Case Engine
- Dedicated data model optimized for CMMN execution
- Same robust architecture as BPMN engine
- Event-driven execution (sentry-based)
- Dynamic case adaptation at runtime
- Human-in-the-loop design
- Full audit trail from start to finish

### Case-Process Integration
- Cases can contain embedded BPMN processes
- Processes can spawn cases
- Bi-directional variable passing
- Shared transaction context
- Event registry for cross-engine events

### Agentic AI Integration (Commercial)
- AI agents embedded directly in CMMN engine
- Orchestrator agents govern case progression
- Agents operate within case context with full audit trail
- Human oversight built into agent actions
- MCP (Model Context Protocol) for agent orchestration

## Architecture

- Case instances have lifecycle: active, suspended, completed, terminated
- Plan items track individual activities within a case
- Stages group related plan items
- Sentries evaluate conditions for state transitions
- Variables scoped to case or plan item level

## Case Management vs Traditional BPM

| Aspect | CMMN (Flowable) | BPMN (Traditional) |
|--------|-----------------|-------------------|
| Flow | Event-driven, non-linear | Sequence-driven, linear |
| Flexibility | High - discretionary items | Low - predefined paths |
| Human Role | Central - knowledge workers | Peripheral - task executors |
| Predictability | Low - emergent behavior | High - predetermined |
| Context | Case holds all context | Scattered across variables |
| Best For | Complex, unpredictable work | Repetitive, structured work |

## Relevance to Procest

### Applicable Patterns
- Case as central organizing concept (Procest's "zaak" concept)
- Event-driven progression (sentries = business rules)
- Human-in-the-loop design philosophy
- Discretionary items for ad-hoc actions
- Full audit trail requirement
- Case-process integration (case spawning subprocesses)

### Key Differences
- Flowable implements full CMMN 1.1 spec; Procest uses a domain-specific case model
- Flowable's cases are generic; Procest's cases follow Dutch government standards (zaakgericht werken)
- Flowable requires Java expertise; Procest leverages Nextcloud's PHP ecosystem

### Opportunities
- Procest can offer a more accessible case management experience through Nextcloud UI
- Focus on zaakgericht werken (Dutch case management) as domain specialization
- Integrate document management natively (Nextcloud Files) vs Flowable's separate content engine
- Provide CMMN-like concepts without requiring CMMN modeling expertise
- Case management + document management in one platform (Flowable separates these)
