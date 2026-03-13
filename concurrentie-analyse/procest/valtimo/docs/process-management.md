# Valtimo Process Management

Sources:
- https://docs.valtimo.nl/features/process
- https://docs.valtimo.nl/features/process/process-link

## Process Engine

Valtimo uses Operaton (community fork of Camunda 7) as its BPMN process engine. In v12.0, Valtimo migrated from Camunda 7 to Operaton.

### BPMN Support
- Full BPMN 2.0 process modeling and execution
- Web-based BPMN modeler for creating/editing process definitions
- Process deployment and versioning
- System processes for internal platform workflows

### DMN Support
- Display, download, and deploy DMN decision tables
- Integrated with process execution

### CMMN
- Partial support via the underlying engine
- Not a primary focus compared to BPMN-driven processes

## Process Links

Process links attach configured actions to Operaton activities, extending BPMN capabilities.

### Link Types
1. **Form** — Presents Form.IO forms on user tasks
2. **Form-flow** — Multi-step form wizards on user tasks
3. **Plugin** — Executes plugin actions with configurable settings
4. **Building block** — Reusable building blocks on call activities

### Configuration Methods
- **UI Configuration:** Admin interface for manual setup
- **IDE Auto-deployment:** JSON files (`<process-id>.process-link.json`) deployed at startup

### Value Resolvers in Process Links
- Fixed values (e.g., `John`)
- Case data: `doc:/firstname`
- Process variables: `pv:firstname`
- Environment variables (require whitelisting)

## Message Correlation
Mechanisms for correlating messages across processes.

## Job Manipulation
Tools for managing job execution within processes.

## Access Control
- `OperatonExecution` resource: `create` action
- `OperatonProcessDefinition` resource: container conditions
- Fine-grained control over who can start which processes
