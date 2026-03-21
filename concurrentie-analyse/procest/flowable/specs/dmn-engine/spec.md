---
competitor: flowable
analyzed_date: 2026-03-14
feature: dmn-engine
module_path: modules/flowable-dmn-engine, modules/flowable-dmn-api, modules/flowable-dmn-model
---

# DMN Engine (Decision Tables)

## Overview

Flowable's DMN engine implements the DMN (Decision Model and Notation) standard for business rule evaluation via decision tables. It can run standalone or integrated with BPMN/CMMN engines via DecisionTask activities.

## Core Service

### DmnDecisionService
The main API for executing decisions:

```java
// Execute with single result (throws if multiple rules match for UNIQUE policy)
Map<String, Object> result = decisionService.executeWithSingleResult(builder);

// Execute with full audit trail
DecisionExecutionAuditContainer audit = decisionService.executeWithAuditTrail(builder);

// Execute returning all matching rows
List<Map<String, Object>> results = decisionService.executeDecision(builder);

// Execute a decision service (multiple linked decisions)
Map<String, List<Map<String, Object>>> results = decisionService.executeDecisionService(builder);
```

### ExecuteDecisionBuilder
Fluent builder for decision execution:
- `decisionKey(String key)` -- decision to execute
- `variables(Map<String, Object>)` -- input variables
- `tenantId(String)` -- tenant scoping
- `parentDeploymentId(String)` -- deployment scoping

## Hit Policies

Flowable implements all 7 DMN hit policies:

| Policy | Class | Behavior |
|--------|-------|----------|
| **UNIQUE** | `HitPolicyUnique` | Exactly one rule must match; error if multiple match |
| **FIRST** | `HitPolicyFirst` | Return the first matching rule (table order) |
| **ANY** | `HitPolicyAny` | All matching rules must produce same output; error otherwise |
| **RULE ORDER** | `HitPolicyRuleOrder` | Return all matching rules in table order |
| **OUTPUT ORDER** | `HitPolicyOutputOrder` | Return all matching rules sorted by output priority |
| **PRIORITY** | `HitPolicyPriority` | Return highest-priority matching rule |
| **COLLECT** | `HitPolicyCollect` | Aggregate results (SUM, MIN, MAX, COUNT, or list) |

## Decision Table Structure

Each decision table consists of:
- **Input columns** -- conditions to evaluate (expressions + variable references)
- **Output columns** -- values to produce when rules match
- **Rules** -- rows combining input conditions with output values
- **Hit policy** -- how to handle multiple matching rules

## Integration Points

### CMMN Integration
- `DecisionTaskActivityBehavior` -- executes DMN from within a CMMN case
- Results stored as case variables automatically
- Configured via `DecisionTask` model element

### BPMN Integration
- `BusinessRuleTask` with DMN reference
- `DecisionTableVariableManager` interface for custom variable mapping
- Results injected into process variables

## Audit Trail

`DecisionExecutionAuditContainer` provides:
- Which rules were evaluated
- Which rules were hit (matched)
- Input values used
- Output values produced
- Execution time
- Any validation messages
- Failed rules information

`DecisionServiceExecutionAuditContainer` extends this for decision services (multiple decisions):
- Audit container per individual decision
- Combined results across decision chain

## Database Tables

- `ACT_DMN_DEPLOYMENT` -- deployment metadata
- `ACT_DMN_DEPLOYMENT_RESOURCE` -- deployed files
- `ACT_DMN_DECISION` -- decision definition versions
- `ACT_DMN_HI_DECISION_EXECUTION` -- execution history

## Procest Comparison

| Feature | Flowable DMN | Procest |
|---------|-------------|---------|
| Decision format | DMN XML decision tables | n8n IF/Switch nodes |
| Hit policies | 7 standard policies | Single IF/Switch evaluation |
| Audit trail | Full decision execution audit | n8n execution log |
| Standalone execution | Yes (API-callable) | n8n workflow trigger |
| Decision chaining | Decision services (multi-table) | n8n sub-workflow |
| Variable types | Typed (string, number, date, boolean) | n8n dynamic typing |
| Integration | Native BPMN/CMMN integration | n8n webhook/API |
