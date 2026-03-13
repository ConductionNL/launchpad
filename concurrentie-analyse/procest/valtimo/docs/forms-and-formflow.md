# Valtimo Forms and Form Flow

Sources:
- https://docs.valtimo.nl/features/case/forms/creating-forms-in-valtimo
- https://docs.valtimo.nl/features/case/form-flow

## Forms (Form.IO)

Valtimo uses Form.IO as its form engine for building user task interfaces.

### Capabilities
- Visual form builder (CRUD page with Form.IO builder)
- Forms linked to user tasks for data input/validation
- JSON-based form definitions
- Custom properties for data binding

### Data Binding (Value Resolvers)
- **sourceKey**: Prefill form fields from external data
- **targetKey**: Store submitted values to specified locations
- Both support `doc:`, `pv:`, `case:`, `zaak:` prefixes

### Prefill Sources
- `doc:person.firstName` — Document JSON path
- `case:assigneeFullName` — Case-level database field
- `pv:lastName` — BPMN process variable
- `zaak:` — Zaken API data
- `zaakstatus:` — Zaak status
- `zaakresultaat:` — Zaak result
- `zaakobject:` — Linked objects

### Prefill Control
- `prefill: false` disables prefilling for specific fields (v10.5.0+)
- `ignoreDisabledFields` controls disabled field behavior

## Form Flow (Multi-Step Wizards)

Form flow creates sequential form experiences similar to wizards or flowcharts.

### Structure
- JSON definitions with a starting step
- Multiple steps with unique keys
- Navigation rules between steps
- Conditional logic for branching

### Navigation
- **Forward:** `nextStep` (single) or `nextSteps` (multiple with conditions)
- **Backward:** Return to previous steps without task completion
- **Conditional:** SpEL expressions determine routing (e.g., `${step.submissionData.personalDetails.age >= 21}`)

### Step Types
- **Form Steps:** Link to Form.IO forms via definition ID
- **Custom Component Steps:** Link to frontend Angular components

### Expression Triggers
- `onOpen` — Execute when entering a step
- `onComplete` — Execute upon step completion
- `onBack` — Execute when navigating backward

### Breadcrumb Navigation
Optional breadcrumb trail showing step titles for quick navigation between stages.
