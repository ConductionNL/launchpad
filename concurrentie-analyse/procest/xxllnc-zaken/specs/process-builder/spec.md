# Process Builder (Procesontwerper)

competitor: xxllnc-zaken
analyzed_date: 2026-03-14
category: workflow-automation
maturity: production
source: https://xxllnc.nl/applicaties/zaken

## Summary

xxllnc Zaken provides a zero-coding process builder that enables functional administrators to design, build, and manage business processes without technical knowledge or vendor intervention. Processes are configured through a management portal and support the full lifecycle from intake to archiving.

## Capabilities

### Process Design
- Zero-coding visual process configuration
- Decision trees (vraagbomen) for conditional logic
- Template-based process creation
- No limit on number of processes
- No technical consequences for configuration changes

### Process Components
- Form design (intake step)
- Case processing steps
- Document creation steps (via templates)
- Communication steps (messages, notifications)
- Archive properties per step
- Involved party management per case

### Administration Portal
- Case type management
- User management with roles and authorizations
- Unlimited user additions
- Integration configuration per process
- Process template library

## Strengths
- True zero-coding approach — no developer needed for process changes
- Self-service for functional administrators
- Unlimited processes and users without additional cost implications
- Integrated with archiving from the start
- Battle-tested with 750 case types at gemeente Utrecht

## Weaknesses
- Process builder is proprietary — no public documentation on exact capabilities
- No BPMN standard compliance mentioned
- No visual process flow editor visible in public materials
- Unclear how complex conditional logic is handled beyond decision trees
- Vendor lock-in: processes are not portable to other systems

## Relevance to Procest

Procest should aim for:
1. **BPMN-based process design** — industry standard, portable
2. **Visual flow editor** — drag-and-drop with clear flow visualization
3. **Open format** — export/import processes as standard notation
4. **Developer extensibility** — zero-coding for simple flows, code for complex ones
5. **Process versioning** — track changes to process definitions over time
