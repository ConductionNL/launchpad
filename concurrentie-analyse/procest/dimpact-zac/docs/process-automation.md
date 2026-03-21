# ZAC Process Automation Architecture

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/processAutomationArchitecture.md

## Engine

Uses embedded **Flowable** open-source process engine supporting both:
- **CMMN** (Case Management Model and Notation) 1.1
- **BPMN** (Business Process Model and Notation) 2.0

## Two Process Types

### 1. Generic CMMN Model

- One generic CMMN model for all "simple" zaaktypes (80-90% of zaaktypes)
- Two main zaak states: **Intake** and **In behandeling** (in progress)
- Tightly integrated with ZAC source code — cannot be changed without code changes
- File: `src/main/resources/cmmn/Generiek_zaakafhandelmodel.cmmn.xml`
- End users cannot edit the CMMN model
- Developers can edit via Flowable Designer or manually

### 2. Custom BPMN Processes

- For more complex zaaktypes that need custom process flows
- Uses **Form.io** web form framework for user task forms
- BPMN process definitions and Form.io forms created externally
- Imported into ZAC via admin interface
- Each zaaktype configured to use either generic CMMN or a specific BPMN process

## Configuration

Every zaaktype must be configured via **zaakafhandelparameters** to use either:
- The generic CMMN model, OR
- A specific BPMN process definition

This is the core configuration mechanism for how cases are handled in ZAC.
