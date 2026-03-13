# Form Logic Engine

## What Open Forms Does

### Logic Rules
`FormLogic` model stores conditional logic per form:
- **Trigger**: `json_logic_trigger` -- JSON Logic expression evaluated against submission data
- **Actions**: array of typed action objects
- **Scoping**: rules can target specific steps, with `trigger_from_step` controlling when evaluation starts
- **Ordering**: rules are ordered (via `OrderedModel`) and evaluated in sequence

### Action Types

| Type | Effect |
|------|--------|
| `property` | Change component property (hidden, disabled, required) |
| `value` | Set a variable's value (JSON Logic expression) |
| `step-not-applicable` | Skip a form step entirely |
| `disable-next` | Prevent user from proceeding to next step |
| `fetch-from-service` | Call external API and store result |
| `evaluate-dmn` | Call DMN engine and map outputs to variables |
| `set-registration-backend` | Switch which registration backend to use |

### Evaluation Flow
1. User completes a step or changes a field value
2. Frontend sends current data to logic evaluation endpoint
3. Backend evaluates all applicable rules in order
4. Returns list of mutations: property changes, value updates, step applicability changes
5. Frontend applies mutations to form state

### DMN Integration
- `DMN` module with plugin system
- Camunda DMN engine supported
- Decision tables evaluated with form variable inputs
- Outputs mapped back to form variables
- Used for complex business rules (e.g., permit requirements, fee calculations)

### Service Fetching
- `ServiceFetchConfiguration` defines external API calls
- Triggered by logic rules of type `fetch-from-service`
- Response mapped to form variables via JSON path expressions
- Supports caching and error handling

### Variable Resolution
- Input variables resolved from form variable keys
- `resolve_key()` handles nested paths (e.g., editgrid items)
- Output variables validated against known form variables

### Execution Graph
- Rules form a dependency graph (input/output variables)
- Graph analysis determines execution order per step
- Prevents circular dependencies

## Already in Procest

- Basic pipeline stage transitions (not conditional)
- n8n workflows can implement business logic externally

## Not Yet in Procest

- **JSON Logic evaluation engine** -- No client-server form logic evaluation
- **Conditional field visibility** -- No dynamic show/hide of form fields
- **Dynamic value calculation** -- No computed fields based on other field values
- **Step skip logic** -- No conditional step applicability
- **DMN decision table evaluation** -- No integration with DMN engines
- **Service fetch in logic** -- No external API calls triggered by form field changes
- **Dynamic registration backend selection** -- No logic-driven backend switching
- **Logic rule ordering and dependency graph** -- No ordered evaluation of interdependent rules
