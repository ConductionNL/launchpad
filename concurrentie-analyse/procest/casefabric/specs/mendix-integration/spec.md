---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Mendix Integration (DCM Add-On)
category: integration
relevance: low
---

# Mendix Integration (DCM Add-On)

## Summary

CaseFabric offers a Dynamic Case Management (DCM) Add-On for the Mendix low-code platform. The case engine runs embedded inside a Mendix application, requiring no additional servers or services. This is CaseFabric's primary commercial integration play.

## Architecture

- Java actions + JAR files added to the Mendix project
- Case engine starts via startup microflow action
- All data stored in the Mendix database (prefixed `casemanagement$`)
- No HTTP API exposed (interaction via Java Actions only)
- Event sourcing happens within the Mendix database

## Key Concepts

### Entity-Case File Integration
- Mendix Entities map to Case File Items
- Entities are used as input/output for case interactions
- Entity changes outside the case file require explicit "Update Case Context" action
- Completion of tasks maps entity data back to case file

### Execution Model
- Uses `CaseManagement_Execution_Queue` Task Queue
- Java actions do preliminary work; actual state changes via queued UserAction
- Ensures Mendix transactions complete before DCM processing
- Issues in execution not directly visible in user action (async)

### Deployment
- Case models in `resources/casemanagement/` directory
- Hot-reload: updated models available without restarting Runtime
- Deployed models bundled as single CMMN XML file

### Studio Pro Plugin
- CaseManagementExtension for Studio Pro IDE
- Visual case model design within Mendix development environment

## Market Positioning
- Available on Mendix Marketplace
- Targets Mendix customers who need case management
- Competes with Mendix's own workflow capabilities
- No additional infrastructure cost

## Relevance to Procest

**Low relevance.** The Mendix integration is specific to that platform. However, the pattern of embedding a case engine within an existing platform is analogous to what Procest does within Nextcloud.

### What to learn:
- Embedding case management into an existing platform (vs. standalone) reduces adoption friction
- Hot-reload of case definitions is a good UX feature
- Mapping between platform entities and case data is a key design challenge
- Async execution queue pattern prevents data integrity issues

### Parallel with Procest:
- Procest embeds case management into Nextcloud (similar pattern)
- OpenRegister objects = Mendix Entities (platform data model)
- n8n workflows = Mendix microflows (automation layer)
- Nextcloud files = case documents (built-in advantage over CaseFabric)
