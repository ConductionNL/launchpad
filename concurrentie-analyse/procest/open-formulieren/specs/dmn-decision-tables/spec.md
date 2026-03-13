# DMN Decision Tables

## What Open Forms Does

### DMN Plugin System
- `dmn.base.BasePlugin` abstract class
- `get_available_decision_definitions()` -- list all definitions
- `evaluate(definition_id, version, input_values)` -- evaluate with inputs
- `get_decision_definition_versions()` -- list versions of a definition
- `DecisionDefinition` and `DecisionDefinitionVersion` dataclasses

### Camunda DMN Plugin
- Connects to Camunda REST API
- Lists decision definitions from Camunda deployment
- Evaluates DMN tables with form variable values as input
- Returns key-value output mapped to form variables

### Usage in Form Logic
- Logic action type `evaluate-dmn` triggers DMN evaluation
- Input variables mapped from form data
- Output variables mapped back to form variables
- Used for: complex eligibility checks, fee calculations, routing decisions, permit requirement determination

### Configuration
- Admin selects DMN plugin, definition, version
- Maps input variables (form keys -> DMN input names)
- Maps output variables (DMN output names -> form keys)

## Already in Procest

- n8n workflows can call external decision services
- No native DMN support

## Not Yet in Procest

- **DMN plugin architecture** -- No pluggable decision table engine
- **Camunda DMN integration** -- No direct Camunda connection
- **Decision table evaluation in form logic** -- No inline DMN calls during form filling
- **Input/output variable mapping** -- No visual mapping of form variables to decision inputs/outputs
