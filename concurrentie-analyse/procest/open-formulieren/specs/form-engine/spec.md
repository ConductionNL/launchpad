# Form Engine

## What Open Forms Does

The core form engine is built around three Django models:

- **Form** (`forms.models.Form`) -- The top-level container. Holds metadata (name, slug, UUID), product link, category, theme FK, payment backend, submission limits, confirmation templates, privacy/truth checkboxes, and all display text overrides (begin, previous, next, confirm, change text).
- **FormDefinition** (`forms.models.FormDefinition`) -- Stores the Form.io JSON configuration (`configuration` JSONField). Definitions can be reusable across forms (`is_reusable`). Tracks component count (`_num_components`). The configuration is a standard Form.io schema containing nested components.
- **FormStep** (`forms.models.FormStep`) -- Ordered through-table linking Form to FormDefinition. Each step has `previous_text`, `save_text`, `next_text` overrides, and an `is_applicable` flag (first step must always be applicable). Steps use `ordered_model.OrderedModel` for ordering. Constraint: each (form, form_definition) pair is unique.

### Form.io Integration
- Form definitions store raw Form.io JSON schemas
- Backend iterates components via `iter_components(configuration, recursive=True)`
- Custom component types beyond standard Form.io: `cosign`, `np-family-members`, `editGrid`, `addressNL`, `bsn`, `iban`, `licenseplate`, `postcode`, `map`, `currency`, `customerProfile`
- Dynamic configuration wrapper (`FormioConfigurationWrapper`) processes visibility, prefill, validation at runtime
- Component translations supported via `translation_enabled` flag on Form

### Form Variables
- `FormVariable` model tracks every variable in a form (source: `component` or `user_defined`)
- Variables carry prefill config (`prefill_plugin`, `prefill_attribute`, `prefill_identifier_role`)
- Data types: string, number, boolean, array, object, date, datetime, time
- Service fetch configuration for user-defined variables (fetching data from external APIs)
- Variables are auto-synchronized from FormDefinition components on save

### Form Logic
- `FormLogic` (ordered model) stores JSON Logic triggers and action arrays per form
- Trigger: `json_logic_trigger` JSONField evaluated with `json_logic` Python library
- Actions: typed operations including `PropertyAction` (change hidden/disabled/required), `SetValueAction`, `DMN evaluation`, `service fetch`, `step not applicable`, `disable next`
- Logic rules can be scoped to specific steps via `form_steps` M2M and `trigger_from_step` FK
- Advanced mode: admin writes raw JSON Logic manually (`is_advanced=True`)

### Form Versioning
- `FormVersion` model stores point-in-time snapshots of the entire form (export blob)
- Created on admin save, allowing rollback

### Form Import/Export
- Forms can be exported as ZIP files and imported into other instances
- Handles FormDefinitions, FormSteps, FormLogic, FormVariables, registration/auth config

## Already in Procest

- Basic form/case model (OpenRegister schemas define form structures)
- Multi-step wizard concept exists in Procest's pipeline stages
- Variable/field concept (OpenRegister object properties)

## Not Yet in Procest

- **Form.io JSON schema engine** -- Procest has no form builder or Form.io integration
- **Reusable form definitions** -- No concept of sharing form step definitions across forms
- **Client-side form rendering** -- Procest does not render interactive forms to citizens
- **Form logic engine (JSON Logic)** -- No conditional field visibility, calculated values, or DMN evaluation
- **Form versioning/snapshots** -- No point-in-time form version history
- **Form import/export** -- No form package transfer between instances
- **Submission limits per form** -- No counter-based submission caps
- **Form variables with prefill bindings** -- No variable-level prefill configuration
- **Service fetch in variables** -- No external API data fetching within form fields
- **Component-level translations** -- No i18n per form component
