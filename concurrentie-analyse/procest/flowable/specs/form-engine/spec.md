---
competitor: flowable
analyzed_date: 2026-03-14
feature: form-engine
module_path: modules/flowable-form-api, modules/flowable-form-model
---

# Form Engine

## Overview

Flowable provides a dedicated form engine for defining and rendering forms attached to human tasks and case start events. Forms are defined as JSON models with typed fields.

## Form Model

### SimpleFormModel
Root form model containing:
- `key` -- unique form identifier
- `name` -- display name
- `version` -- form version
- `fields` -- list of `FormField` definitions
- `outcomes` -- list of `FormOutcome` options (submit button options)

### FormField
Base field type with properties:
- `id` -- field identifier (maps to variable name)
- `name` -- display label
- `type` -- field type string
- `value` -- default/current value
- `required` -- validation flag
- `readOnly` -- display-only flag
- `overrideId` -- allows custom variable mapping
- `placeholder` -- input placeholder text
- `params` -- arbitrary key-value parameters for extensions
- `layout` -- `LayoutDefinition` for positioning

### Field Types (FormFieldTypes)
The form model supports these built-in types:
- Standard inputs: text, number, date, boolean, dropdown, radio
- Advanced: expression (computed fields), container (nested layout)
- Options: `OptionFormField` with list of `Option` (id + name pairs)

### FormContainer
A special field type for grouping fields into layout sections/columns.

### FormOutcome
Submit button options:
- `id` -- outcome identifier
- `name` -- display label
Allows forms to have multiple submit paths (e.g., "Approve" / "Reject" / "Request Info").

### LayoutDefinition
Positioning within form grid:
- Row and column placement
- Used for responsive form layouts

## Integration Points

### CMMN Integration
- `HumanTask.formKey` references a form definition
- `Stage.formKey` for plan model start forms
- `CmmnRuntimeService.getStartFormModel()` retrieves case start form
- `CmmnTaskService.getTaskFormModel()` retrieves task form
- `CmmnTaskService.completeTaskWithForm()` validates and completes with form data
- Form field validation configurable: `validateFormFields` property on HumanTask

### BPMN Integration
- `FormService.getStartFormModel()` / `getTaskFormModel()`
- Start events and user tasks can reference form definitions
- Form data maps to process variables

### Validation
- `sameDeployment` flag ensures form and case/process are co-deployed
- `validateFormFields` controls validation strictness ("all", "required", or custom)

## Procest Comparison

| Feature | Flowable Forms | Procest |
|---------|---------------|---------|
| Form definition | JSON model (SimpleFormModel) | Nextcloud Forms / Vue components |
| Field types | ~6 built-in types | Nextcloud form field types |
| Outcomes | Multiple submit options | Custom submit handling |
| Validation | Built-in required/type validation | Frontend validation |
| Form versioning | Co-deployed with case/process | Not versioned |
| Expression fields | Computed fields via expressions | Not available |
| Layout | Grid-based LayoutDefinition | CSS-based |
| Form-task binding | formKey reference | OpenRegister schema |
