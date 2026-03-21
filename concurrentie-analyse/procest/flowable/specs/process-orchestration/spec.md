---
competitor: flowable
analyzed_date: 2026-03-14
feature: Process Orchestration
category: platform
---

# Process Orchestration

## What It Is

Flowable's process orchestration capability connects agents, people, and processes across systems. It provides end-to-end visibility and control over business operations, now enhanced with agentic AI.

## Key Capabilities

### End-to-End Orchestration
- Coordinate across multiple engines (BPMN + CMMN + DMN)
- Cross-system integration via connectors
- Unified task management across process types
- Real-time monitoring and adaptation

### Enterprise Connectivity
- Database connectors (instant integration)
- RESTful data source integration
- Enterprise Content Management integration
- Pre-built connectors for common systems
- Custom connector development

### Process Monitoring (Flowable Control)
- Runtime engine monitoring dashboard
- Process analytics and reporting
- Report generation and download
- Visual debugging with Inspect
- SLA tracking and alerting

### Low-Code Development
- Drag-and-drop modeling (Flowable Design)
- Visual expression builders
- Variable autocompletion
- Form designer
- No-code integration configuration

## Platform Architecture

Flowable Platform consists of three main applications:

1. **Flowable Work** - Runtime execution and user interface
   - Task inbox and management
   - Case/process views
   - Document management
   - User/group management

2. **Flowable Design** - Modeling and development
   - BPMN process modeler
   - CMMN case modeler
   - DMN decision table editor
   - Form designer
   - App deployment

3. **Flowable Control** - Administration and monitoring
   - Engine status monitoring
   - Process analytics
   - Report generation
   - Job management
   - Deployment management

## Relevance to Procest

### Applicable Patterns
- Unified orchestration of different work types (cases, processes, decisions)
- Monitor/control dashboard for operational insight
- Connector architecture for system integration
- Low-code form and workflow design

### Key Differences
- Flowable provides all three apps (Work/Design/Control) in one platform
- Procest uses Nextcloud as the "Work" environment and n8n for orchestration
- Flowable's monitoring is built-in; Procest would need separate dashboarding

### Opportunities
- Procest can leverage Nextcloud's existing collaboration features as "Work"
- n8n provides 400+ integration nodes vs Flowable's smaller connector library
- Nextcloud Dashboard can serve as the monitoring/control interface
- Procest can offer a more integrated experience (files + tasks + processes in one)
