---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Form System (Form.io + FormFlow) -- Valtimo

## Purpose
Provides the user-facing data entry layer for case management. Form.io delivers drag-and-drop form building with JSON-based definitions, while FormFlow adds multi-step wizard capabilities with conditional navigation. Together they enable complex data collection workflows without custom frontend development.

## Architecture Overview
- **Backend modules**: `form/` (Form.io management), `form-flow/` (wizard engine), `form-view-model/` (dynamic binding)
- **Frontend modules**: `form/` (Form.io renderer), `form-management/` (CRUD), `form-flow-management/` (wizard config), `form-view-model/` (view model UI)
- **Form engine**: Form.io (open-source form renderer, JSON-driven)
- **Data binding**: Value resolver pattern (`doc:`, `pv:`, `case:`, `zaak:` prefixes)
- **Integration**: Forms linked to BPMN user tasks via ProcessLinks

## Data Model

### FormDefinition (Form.io)
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique form ID |
| name | String | Form name/key |
| formDefinition | JSON | Form.io JSON definition (components, layout, validation) |
| readOnly | Boolean | Whether the form is read-only |

### FormFlowDefinition
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique definition ID |
| key | String | Form flow identifier |
| startStep | String | Key of the first step |
| steps | JSON | Map of step key to step definition |

### FormFlowStep
| Field | Type | Description |
|-------|------|-------------|
| key | String | Unique step identifier within the flow |
| type | Enum | FORM (Form.io) or CUSTOM_COMPONENT (Angular) |
| typeProperties | JSON | Reference to form definition or component |
| nextStep | String | Default next step key (single path) |
| nextSteps | List | Conditional next steps with SpEL expressions |
| onOpen | List | Expressions to execute on step entry |
| onComplete | List | Expressions to execute on step completion |
| onBack | List | Expressions to execute on backward navigation |

### FormFlowInstance (runtime)
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Instance ID |
| definitionId | UUID | Reference to FormFlowDefinition |
| currentStepKey | String | Currently active step |
| submissionData | JSON | Accumulated submission data across steps |
| history | List | Stack of visited steps (for back navigation) |

## Business Logic

### Form.io Form Rendering
1. BPMN user task reached; ProcessLink resolves to a FORM type
2. Form definition fetched by name/ID
3. **Prefill**: Value resolvers populate form fields from multiple sources:
   - `doc:/person/firstName` -- from case JSON document
   - `pv:processVariable` -- from BPMN process variable
   - `case:assigneeFullName` -- from case-level database field
   - `zaak:startdatum` -- from external ZGW zaak
4. Form rendered to user via Form.io Angular component
5. **Submission**: Value resolvers write data back to configured targets
6. Task completed, process continues

### FormFlow Wizard Execution
1. User task linked to a FORM_FLOW ProcessLink
2. FormFlowInstance created, starting at `startStep`
3. Each step renders its form or custom component
4. On step completion:
   - `onComplete` expressions evaluated
   - Submission data accumulated in instance
   - Next step determined: `nextStep` (direct) or `nextSteps` (conditional via SpEL)
5. Back navigation: pop history stack, execute `onBack` expressions
6. Final step submission completes the user task

### Conditional Navigation (SpEL)
```
nextSteps:
  - step: "adult-form"
    condition: "${step.submissionData.personalDetails.age >= 18}"
  - step: "minor-form"
    condition: "${step.submissionData.personalDetails.age < 18}"
```

### Intermediate Save
- Form progress can be saved before completing the task
- Data persisted to the form flow instance
- User can return and resume later

### Breadcrumb Navigation
- Optional breadcrumb trail showing step titles
- Allows direct navigation to previously completed steps

### Auto-Deployment
- Form definitions deployed from `*.form` JSON files
- Form flow definitions deployed from `*.formflow.json` files

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **OpenRegister schemas** for data structure definition
- Frontend forms auto-generated from JSON Schema (Vue.js components)
- No dedicated form builder -- forms derived from schema
- No multi-step wizard functionality (single-page forms)

### Valtimo advantages
- Drag-and-drop form builder (Form.io) for non-technical users
- Multi-step wizard forms with conditional branching (FormFlow)
- Rich prefill/submission binding via value resolvers
- Intermediate save for long forms
- Breadcrumb navigation in wizards
- Form view models for dynamic data binding

### Valtimo disadvantages
- Form.io adds significant frontend bundle size
- Form definitions are separate from data schema (can drift out of sync)
- Two separate systems (Form.io + FormFlow) add complexity
- Form.io is an external dependency with its own release cycle
- No native NL Design System support in forms (uses Form.io's styling)
