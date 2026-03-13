# Open Formulieren — Form Builder

## Overview

The form builder is the core feature of Open Formulieren. It provides a no-code/low-code drag-and-drop interface for administrators to create multi-step forms with conditional logic, validation, and smart field behavior.

## Built on Form.io

Open Formulieren uses a customized fork of the form.io JavaScript library (`@open-formulieren/formiojs`) as its form rendering engine. Form definitions are stored as JSON schemas compatible with the form.io specification, extended with Open Forms-specific properties.

## Form Components

### Basic Fields
- Text field (short text)
- Text area (multiline)
- Number field
- Email field
- Phone number
- Date picker
- Time picker
- Checkbox
- Select / dropdown
- Radio buttons
- File upload

### Advanced Fields
- **Map component** — Geo-location with point markers, lines, and polygon drawing (since v3.1)
- **BSN field** — Dutch citizen service number with validation
- **IBAN field** — International bank account number
- **Postcode field** — Dutch postal code with address lookup
- **Cosign component** — Allows a second person to co-sign the submission
- **Signature component** — Digital signature capture
- **Repeating groups** — Dynamically add/remove sets of fields

### Layout Components
- Fieldset (grouping)
- Columns (multi-column layout)
- Content (static HTML/text blocks)
- Step separators (multi-page forms)

## Logic Rules

The form builder supports no-code logic rules for dynamic form behavior:

- **Show/hide fields** — Conditionally display fields based on other field values
- **Enable/disable fields** — Make fields read-only based on conditions
- **Change field values** — Auto-populate fields based on logic
- **Require/unrequire fields** — Dynamic required validation
- **Step navigation** — Skip steps based on conditions
- **JSON Logic** — Advanced logic using JSON Logic expressions for complex conditions

## Multi-Step Forms

- Forms can be divided into multiple steps/pages
- Progress indicator shows current position
- "Save and continue later" allows users to resume incomplete forms
- Navigation buttons: Next, Previous, Continue later, Log out
- Step visibility can be controlled by logic rules

## Validation

- Built-in validation per component type (email format, BSN check digit, IBAN, etc.)
- Custom validation rules via regex patterns
- Required field validation
- Min/max length, min/max value constraints
- Custom error messages per validation rule

## Comparison with Procest

| Feature | Open Formulieren | Procest |
|---------|-----------------|---------|
| Drag-and-drop form builder | Yes (full visual editor) | No (case forms are ZGW-driven) |
| Conditional logic (no-code) | Yes (JSON Logic engine) | No |
| Multi-step forms | Yes | No |
| Save and resume | Yes | No |
| Map/geo components | Yes (point, line, polygon) | No |
| File upload in forms | Yes | Yes (via DRC) |
| BSN/IBAN validation | Yes (built-in) | No |
| Address/postcode lookup | Yes | No |
| Digital signature | Yes | No |
| Repeating groups | Yes | No |
| Form preview | Yes | No |
| Form versioning | Yes | No |
| Form import/export | Yes (JSON) | No |

### Analysis

Open Formulieren's form builder is its primary competitive strength and is far more advanced than anything Procest currently offers. Procest does not have a form builder — it focuses on case management after data has been submitted. For Procest to compete in the intake space, it would need either:

1. **Integration approach:** Accept submissions from Open Formulieren via ZGW APIs (complementary)
2. **Build approach:** Create a form builder component within Nextcloud, potentially using form.io or a similar library
3. **Hybrid approach:** Use OpenRegister schemas to define simple intake forms that feed directly into case workflows
